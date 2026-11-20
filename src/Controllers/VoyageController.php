
<?php

final class VoyageController
{
    private static function user(): array
    {
        Access::requireAuth();
        return Auth::user();
    }

    private static function canManage(): void
    {
        Access::requirePermission('voyage.manage');
    }

    private static function csrf(): void
    {
        Request::verifyCsrf();
    }

    private static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private static function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['flash_message'] = [
            'message' => $message,
            'type' => $type
        ];
    }

    private static function scope(PDO $pdo): array
    {
        $user = self::user();
        $portees = $user['portees'] ?? [];

        if (in_array('PLATEFORME', $portees, true)) {
            return [
                'type' => 'PLATEFORME',
                'id' => null
            ];
        }

        $stmt = $pdo->prepare("
            SELECT id_entreprise
            FROM agence
            WHERE id_agence = :agence
        ");
        $stmt->execute(['agence' => $user['id_agence']]);

        $enterprise = $stmt->fetchColumn();

        if (!$enterprise) {
            throw new RuntimeException('Périmètre agence introuvable.');
        }

        if (in_array('ENTREPRISE', $portees, true)) {
            return [
                'type' => 'ENTREPRISE',
                'id' => $enterprise
            ];
        }

        return [
            'type' => 'AGENCE',
            'id' => $user['id_agence']
        ];
    }

    private static function itineraryAllowed(
        PDO $pdo,
        string $idItineraire
    ): bool {
        $scope = self::scope($pdo);

        $sql = "
            SELECT COUNT(*)
            FROM agence_itineraire ai
            INNER JOIN agence a
                ON a.id_agence = ai.id_agence
            INNER JOIN itineraire i
                ON i.id_itineraire = ai.id_itineraire
            WHERE ai.id_itineraire = :itineraire
              AND i.statut = 'ACTIF'
        ";

        $params = ['itineraire' => $idItineraire];

        if ($scope['type'] === 'AGENCE') {
            $sql .= " AND ai.id_agence = :scope";
            $params['scope'] = $scope['id'];
        } elseif ($scope['type'] === 'ENTREPRISE') {
            $sql .= " AND a.id_entreprise = :scope";
            $params['scope'] = $scope['id'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    private static function voyage(PDO $pdo, string $id): array
    {
        $scope = self::scope($pdo);

        $sql = "
            SELECT v.*
            FROM voyage v
            INNER JOIN agence_itineraire ai
                ON ai.id_itineraire = v.id_itineraire
            INNER JOIN agence a
                ON a.id_agence = ai.id_agence
            WHERE v.id_voyage = :id
        ";

        $params = ['id' => $id];

        if ($scope['type'] === 'AGENCE') {
            $sql .= " AND ai.id_agence = :scope";
            $params['scope'] = $scope['id'];
        } elseif ($scope['type'] === 'ENTREPRISE') {
            $sql .= " AND a.id_entreprise = :scope";
            $params['scope'] = $scope['id'];
        }

        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $voyage = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voyage) {
            http_response_code(404);
            exit('Voyage introuvable ou accès refusé.');
        }

        return $voyage;
    }

    private static function activeReservations(PDO $pdo, string $id): int
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reservation
        WHERE id_voyage = :id
          AND statut IN ('EN ATTENTE', 'CONFIRMEE')
    ");

    $stmt->execute(['id' => $id]);

    return (int) $stmt->fetchColumn();
}

    private static function vehicleAllowed(PDO $pdo, string $immatriculation): bool
    {
        $scope = self::scope($pdo);

        $sql = "
            SELECT COUNT(*)
            FROM vehicule v
            INNER JOIN agence a
                ON a.id_entreprise = v.id_entreprise
            WHERE v.immatriculation = :immat
              AND v.etat_operationnel = 'OPERATIONNEL'
        ";

        $params = ['immat' => $immatriculation];

        if ($scope['type'] === 'AGENCE') {
            $sql .= " AND a.id_agence = :scope";
            $params['scope'] = $scope['id'];
        } elseif ($scope['type'] === 'ENTREPRISE') {
            $sql .= " AND a.id_entreprise = :scope";
            $params['scope'] = $scope['id'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function index(PDO $pdo): void
    {
        Access::requireAnyPermission([
            'voyage.view',
            'voyage.manage',
            'itinerary.manage'
        ]);

        $user = self::user();
        $scope = self::scope($pdo);

        $search = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? '');

        $sql = "
            SELECT
                vd.id_voyage,
                vd.itineraire,
                vd.date_voyage,
                vd.heure_depart_prevue,
                vd.heure_arrivee_prevue,
                vd.statut,
                vd.immatriculation,
                vd.marque,
                vd.modele,
                vd.chauffeurs,
                vd.escales,
                vp.capacite_totale,
                vp.places_prises,
                vp.places_libres
            FROM v_voyage_detail vd
            INNER JOIN v_places_voyage vp
                ON vp.id_voyage = vd.id_voyage
            WHERE EXISTS (
                SELECT 1
                FROM voyage v
                INNER JOIN agence_itineraire ai
                    ON ai.id_itineraire = v.id_itineraire
                INNER JOIN agence a
                    ON a.id_agence = ai.id_agence
                WHERE v.id_voyage = vd.id_voyage
        ";

        $params = [];

        if ($scope['type'] === 'AGENCE') {
            $sql .= " AND ai.id_agence = :scope";
            $params['scope'] = $scope['id'];
        } elseif ($scope['type'] === 'ENTREPRISE') {
            $sql .= " AND a.id_entreprise = :scope";
            $params['scope'] = $scope['id'];
        }

        $sql .= ")";

        if ($search !== '') {
            $sql .= "
                AND (
                    vd.id_voyage LIKE :s1
                    OR vd.itineraire LIKE :s2
                    OR vd.immatriculation LIKE :s3
                    OR vd.chauffeurs LIKE :s4
                )
            ";

            $term = '%' . $search . '%';

            $params['s1'] = $term;
            $params['s2'] = $term;
            $params['s3'] = $term;
            $params['s4'] = $term;
        }

        $allowedStatuses = [
            'PROGRAMME',
            'EN COURS',
            'TERMINE',
            'ANNULE'
        ];

        if (in_array($status, $allowedStatuses, true)) {
            $sql .= " AND vd.statut = :status";
            $params['status'] = $status;
        }

        $sql .= "
            ORDER BY vd.date_voyage DESC,
                     vd.heure_depart_prevue DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $csrfToken = Csrf::token();

        require dirname(__DIR__, 2) . '/templates/voyages/index.php';
    }

    public static function show(PDO $pdo): void
    {
        Access::requireAnyPermission([
            'voyage.view',
            'voyage.manage',
            'itinerary.manage'
        ]);

        $id = trim($_GET['id'] ?? '');
        $voyage = self::voyage($pdo, $id);

        $stmt = $pdo->prepare("
            SELECT *
            FROM v_voyage_detail
            WHERE id_voyage = :id
        ");
        $stmt->execute(['id' => $id]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT *
            FROM v_places_voyage
            WHERE id_voyage = :id
        ");
        $stmt->execute(['id' => $id]);
        $places = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT vc.id_chauffeur, c.nom, c.prenom,
                   c.telephone, c.numero_permis
            FROM voyage_chauffeur vc
            INNER JOIN chauffeur c
                ON c.id_chauffeur = vc.id_chauffeur
            WHERE vc.id_voyage = :id
        ");
        $stmt->execute(['id' => $id]);
        $chauffeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT ve.id_escale, ve.ordre_passage,
                   ve.heure_passage_prevue,
                   e.nom_escale, vi.nom_ville
            FROM voyage_escale ve
            INNER JOIN escale e ON e.id_escale = ve.id_escale
            INNER JOIN ville vi ON vi.id_ville = e.id_ville
            WHERE ve.id_voyage = :id
            ORDER BY ve.ordre_passage
        ");
        $stmt->execute(['id' => $id]);
        $escales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $canManage = Access::hasPermission('voyage.manage');
        $csrfToken = Csrf::token();

        require dirname(__DIR__, 2) . '/templates/voyages/show.php';
    }

    public static function create(PDO $pdo): void
    {
        self::canManage();

        $scope = self::scope($pdo);

        $sql = "
            SELECT DISTINCT i.id_itineraire, i.libelle
            FROM itineraire i
            INNER JOIN agence_itineraire ai
                ON ai.id_itineraire = i.id_itineraire
            INNER JOIN agence a ON a.id_agence = ai.id_agence
            WHERE i.statut = 'ACTIF'
        ";

        $params = [];

        if ($scope['type'] === 'AGENCE') {
            $sql .= " AND ai.id_agence = :scope";
            $params['scope'] = $scope['id'];
        } elseif ($scope['type'] === 'ENTREPRISE') {
            $sql .= " AND a.id_entreprise = :scope";
            $params['scope'] = $scope['id'];
        }

        $stmt = $pdo->prepare($sql . " ORDER BY i.libelle");
        $stmt->execute($params);
        $itineraires = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "
            SELECT DISTINCT v.immatriculation, v.marque, v.modele,
                   v.capacite_totale
            FROM vehicule v
            INNER JOIN agence a ON a.id_entreprise = v.id_entreprise
            WHERE v.etat_operationnel = 'OPERATIONNEL'
        ";

        $params = [];

        if ($scope['type'] === 'AGENCE') {
            $sql .= " AND a.id_agence = :scope";
            $params['scope'] = $scope['id'];
        } elseif ($scope['type'] === 'ENTREPRISE') {
            $sql .= " AND a.id_entreprise = :scope";
            $params['scope'] = $scope['id'];
        }

        $stmt = $pdo->prepare($sql . " ORDER BY v.immatriculation");
        $stmt->execute($params);
        $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $csrfToken = Csrf::token();
        $error = null;

        require dirname(__DIR__, 2) . '/templates/voyages/create.php';
    }

    public static function store(PDO $pdo): void
    {
        self::canManage();
        self::csrf();

        $itineraire = trim($_POST['id_itineraire'] ?? '');
        $immat = trim($_POST['immatriculation'] ?? '');
        $date = trim($_POST['date_voyage'] ?? '');
        $depart = trim($_POST['heure_depart_prevue'] ?? '');
        $arrivee = trim($_POST['heure_arrivee_prevue'] ?? '');

        if (
            $itineraire === '' ||
            $immat === '' ||
            !self::itineraryAllowed($pdo, $itineraire) ||
            !self::vehicleAllowed($pdo, $immat)
        ) {
            self::flash('Itinéraire ou véhicule invalide.', 'error');
            self::redirect('/voyages/create');
        }

        $dateObj = DateTime::createFromFormat('!Y-m-d', $date);
        $time1 = DateTime::createFromFormat('!H:i', $depart);
        $time2 = DateTime::createFromFormat('!H:i', $arrivee);

        if (
            !$dateObj || $dateObj->format('Y-m-d') !== $date ||
            !$time1 || !$time2 || $time2 <= $time1
        ) {
            self::flash('Date ou horaires invalides.', 'error');
            self::redirect('/voyages/create');
        }

        $id = 'VYG-' . strtoupper(bin2hex(random_bytes(5)));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO voyage (
                    id_voyage,
                    id_itineraire,
                    immatriculation,
                    date_voyage,
                    heure_depart_prevue,
                    heure_arrivee_prevue,
                    statut
                ) VALUES (
                    :id, :itineraire, :immat, :date,
                    :depart, :arrivee, 'PROGRAMME'
                )
            ");

            $stmt->execute([
                'id' => $id,
                'itineraire' => $itineraire,
                'immat' => $immat,
                'date' => $date,
                'depart' => $depart,
                'arrivee' => $arrivee
            ]);

            self::flash('Voyage créé avec succès.');
            self::redirect('/voyages/show?id=' . urlencode($id));

        } catch (PDOException $e) {
            self::flash(
                'Création impossible : véhicule indisponible, conflit horaire ou données invalides.',
                'error'
            );
            self::redirect('/voyages/create');
        }
    }

    public static function edit(PDO $pdo): void
    {
        self::canManage();

        $id = trim($_GET['id'] ?? '');
        $voyage = self::voyage($pdo, $id);

        if ($voyage['statut'] !== 'PROGRAMME') {
            self::flash('Seuls les voyages programmés peuvent être modifiés.', 'error');
            self::redirect('/voyages/show?id=' . urlencode($id));
        }

        $stmt = $pdo->prepare("
            SELECT DISTINCT i.id_itineraire, i.libelle
            FROM itineraire i
            INNER JOIN agence_itineraire ai
                ON ai.id_itineraire = i.id_itineraire
            INNER JOIN agence a ON a.id_agence = ai.id_agence
            WHERE i.statut = 'ACTIF'
        ");

        $scope = self::scope($pdo);
        $params = [];

        if ($scope['type'] === 'AGENCE') {
            $stmt = $pdo->prepare("
                SELECT DISTINCT i.id_itineraire, i.libelle
                FROM itineraire i
                INNER JOIN agence_itineraire ai
                    ON ai.id_itineraire = i.id_itineraire
                WHERE i.statut = 'ACTIF'
                  AND ai.id_agence = :scope
            ");
            $params['scope'] = $scope['id'];
        } elseif ($scope['type'] === 'ENTREPRISE') {
            $stmt = $pdo->prepare("
                SELECT DISTINCT i.id_itineraire, i.libelle
                FROM itineraire i
                INNER JOIN agence_itineraire ai
                    ON ai.id_itineraire = i.id_itineraire
                INNER JOIN agence a ON a.id_agence = ai.id_agence
                WHERE i.statut = 'ACTIF'
                  AND a.id_entreprise = :scope
            ");
            $params['scope'] = $scope['id'];
        } else {
            $stmt = $pdo->prepare("
                SELECT id_itineraire, libelle
                FROM itineraire
                WHERE statut = 'ACTIF'
            ");
        }

        $stmt->execute($params);
        $itineraires = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sql = "
            SELECT DISTINCT v.immatriculation, v.marque, v.modele,
                   v.capacite_totale
            FROM vehicule v
            INNER JOIN agence a ON a.id_entreprise = v.id_entreprise
            WHERE v.etat_operationnel = 'OPERATIONNEL'
        ";

        $params = [];

        if ($scope['type'] === 'AGENCE') {
            $sql .= " AND a.id_agence = :scope";
            $params['scope'] = $scope['id'];
        } elseif ($scope['type'] === 'ENTREPRISE') {
            $sql .= " AND a.id_entreprise = :scope";
            $params['scope'] = $scope['id'];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vehicules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $csrfToken = Csrf::token();
        $error = null;

        require dirname(__DIR__, 2) . '/templates/voyages/edit.php';
    }

    public static function update(PDO $pdo): void
    {
        self::canManage();
        self::csrf();

        $id = trim($_POST['id_voyage'] ?? '');
        $voyage = self::voyage($pdo, $id);

        if ($voyage['statut'] !== 'PROGRAMME') {
            self::flash('Ce voyage ne peut plus être modifié.', 'error');
            self::redirect('/voyages/show?id=' . urlencode($id));
        }

        if (self::activeReservations($pdo, $id) > 0) {
            self::flash(
                'Modification interdite : le voyage possède des réservations confirmées.',
                'error'
            );
            self::redirect('/voyages/show?id=' . urlencode($id));
        }

        $itineraire = trim($_POST['id_itineraire'] ?? '');
        $immat = trim($_POST['immatriculation'] ?? '');
        $date = trim($_POST['date_voyage'] ?? '');
        $depart = trim($_POST['heure_depart_prevue'] ?? '');
        $arrivee = trim($_POST['heure_arrivee_prevue'] ?? '');

        if (
            !self::itineraryAllowed($pdo, $itineraire) ||
            !self::vehicleAllowed($pdo, $immat)
        ) {
            self::flash('Itinéraire ou véhicule invalide.', 'error');
            self::redirect('/voyages/edit?id=' . urlencode($id));
        }

        $dateObj = DateTime::createFromFormat('!Y-m-d', $date);
        $time1 = DateTime::createFromFormat('!H:i', $depart);
        $time2 = DateTime::createFromFormat('!H:i', $arrivee);

        if (
            !$dateObj || $dateObj->format('Y-m-d') !== $date ||
            !$time1 || !$time2 || $time2 <= $time1
        ) {
            self::flash('Date ou horaires invalides.', 'error');
            self::redirect('/voyages/edit?id=' . urlencode($id));
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE voyage
                SET id_itineraire = :itineraire,
                    immatriculation = :immat,
                    date_voyage = :date,
                    heure_depart_prevue = :depart,
                    heure_arrivee_prevue = :arrivee
                WHERE id_voyage = :id
                  AND statut = 'PROGRAMME'
            ");

            $stmt->execute([
                'itineraire' => $itineraire,
                'immat' => $immat,
                'date' => $date,
                'depart' => $depart,
                'arrivee' => $arrivee,
                'id' => $id
            ]);

            self::flash('Voyage modifié.');
            self::redirect('/voyages/show?id=' . urlencode($id));

        } catch (PDOException $e) {
            self::flash('Modification impossible : conflit ou données invalides.', 'error');
            self::redirect('/voyages/edit?id=' . urlencode($id));
        }
    }

    public static function addDriver(PDO $pdo): void
{
    self::canManage();
    self::csrf();

    $id = trim($_POST['id_voyage'] ?? '');
    $chauffeur = trim($_POST['id_chauffeur'] ?? '');

    if ($id === '' || $chauffeur === '') {
        self::flash('Informations manquantes.', 'error');
        self::redirect('/voyages');
    }

    $voyage = self::voyage($pdo, $id);

    if ($voyage['statut'] !== 'PROGRAMME') {
        self::flash('Affectation impossible : voyage non programmé.', 'error');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    $scope = self::scope($pdo);

    $sql = "
        SELECT c.id_chauffeur, c.statut
        FROM chauffeur c
        INNER JOIN employe e ON e.id_employe = c.id_employe
        INNER JOIN agence a ON a.id_agence = e.id_agence
        WHERE c.id_chauffeur = :chauffeur
    ";

    $params = ['chauffeur' => $chauffeur];

    if ($scope['type'] === 'AGENCE') {
        $sql .= " AND e.id_agence = :scope";
        $params['scope'] = $scope['id'];
    } elseif ($scope['type'] === 'ENTREPRISE') {
        $sql .= " AND a.id_entreprise = :scope";
        $params['scope'] = $scope['id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $driver = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$driver) {
        self::flash('Chauffeur introuvable ou hors périmètre.', 'error');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    if ($driver['statut'] !== 'DISPONIBLE') {
        self::flash('Le chauffeur doit avoir le statut DISPONIBLE.', 'error');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM voyage_chauffeur vc
        INNER JOIN voyage v ON v.id_voyage = vc.id_voyage
        WHERE vc.id_chauffeur = :chauffeur
          AND v.id_voyage <> :voyage
          AND v.statut <> 'ANNULE'
          AND v.date_voyage = :date
          AND v.heure_depart_prevue < :arrivee
          AND v.heure_arrivee_prevue > :depart
    ");

    $stmt->execute([
        'chauffeur' => $chauffeur,
        'voyage' => $id,
        'date' => $voyage['date_voyage'],
        'arrivee' => $voyage['heure_arrivee_prevue'],
        'depart' => $voyage['heure_depart_prevue']
    ]);

    if ((int) $stmt->fetchColumn() > 0) {
        self::flash('Conflit horaire : chauffeur déjà affecté.', 'error');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO voyage_chauffeur (
                id_voyage,
                id_chauffeur
            )
            VALUES (:voyage, :chauffeur)
        ");

        $stmt->execute([
            'voyage' => $id,
            'chauffeur' => $chauffeur
        ]);

        self::flash('Chauffeur affecté avec succès.');

    } catch (PDOException $e) {
        self::flash(
            'Affectation impossible : doublon ou erreur de données.',
            'error'
        );
    }

    self::redirect('/voyages/show?id=' . urlencode($id));
}

    public static function removeDriver(PDO $pdo): void
    {
        self::canManage();
        self::csrf();

        $id = trim($_POST['id_voyage'] ?? '');
        $chauffeur = trim($_POST['id_chauffeur'] ?? '');

        $voyage = self::voyage($pdo, $id);

        if ($voyage['statut'] !== 'PROGRAMME') {
            self::flash('Retrait impossible : voyage non programmé.', 'error');
            self::redirect('/voyages/show?id=' . urlencode($id));
        }

        $stmt = $pdo->prepare("
            DELETE FROM voyage_chauffeur
            WHERE id_voyage = :voyage
              AND id_chauffeur = :chauffeur
        ");

        $stmt->execute([
            'voyage' => $id,
            'chauffeur' => $chauffeur
        ]);

        self::flash('Chauffeur retiré.');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    public static function addStop(PDO $pdo): void
{
    self::canManage();
    self::csrf();

    $id = trim($_POST['id_voyage'] ?? '');
    $escale = trim($_POST['id_escale'] ?? '');
    $heure = trim($_POST['heure_passage_prevue'] ?? '');

    if ($id === '' || $escale === '') {
        self::flash('Informations manquantes.', 'error');
        self::redirect('/voyages');
    }

    $voyage = self::voyage($pdo, $id);

    if ($voyage['statut'] !== 'PROGRAMME') {
        self::flash("Modification des escales interdite.", 'error');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    // Validation stricte de l'heure
    if ($heure !== '') {
        $time = DateTime::createFromFormat('!H:i', $heure);

        if (!$time || $time->format('H:i') !== $heure) {
            self::flash('Heure de passage invalide.', 'error');
            self::redirect('/voyages/show?id=' . urlencode($id));
        }
    }

    // Vérification de l'existence de l'escale
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM escale
        WHERE id_escale = :id
    ");

    $stmt->execute(['id' => $escale]);

    if ((int) $stmt->fetchColumn() === 0) {
        self::flash('Escale inconnue.', 'error');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    // Calcul du prochain ordre
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(ordre_passage), 0) + 1
        FROM voyage_escale
        WHERE id_voyage = :id
    ");

    $stmt->execute(['id' => $id]);

    $ordre = (int) $stmt->fetchColumn();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO voyage_escale (
                id_voyage,
                id_escale,
                ordre_passage,
                heure_passage_prevue
            )
            VALUES (
                :voyage,
                :escale,
                :ordre,
                :heure
            )
        ");

        $stmt->execute([
            'voyage' => $id,
            'escale' => $escale,
            'ordre' => $ordre,
            'heure' => $heure !== '' ? $heure : null
        ]);

        self::flash('Escale ajoutée avec succès.');

    } catch (PDOException $e) {
        self::flash(
            'Cette escale existe déjà ou les données sont invalides.',
            'error'
        );
    }

    self::redirect('/voyages/show?id=' . urlencode($id));
}

    public static function removeStop(PDO $pdo): void
    {
        self::canManage();
        self::csrf();

        $id = trim($_POST['id_voyage'] ?? '');
        $escale = trim($_POST['id_escale'] ?? '');

        $voyage = self::voyage($pdo, $id);

        if ($voyage['statut'] !== 'PROGRAMME') {
            self::flash("Suppression d'escale interdite.", 'error');
            self::redirect('/voyages/show?id=' . urlencode($id));
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                DELETE FROM voyage_escale
                WHERE id_voyage = :id
                  AND id_escale = :escale
            ");
            $stmt->execute([
                'id' => $id,
                'escale' => $escale
            ]);

            $stmt = $pdo->prepare("
                SELECT id_escale
                FROM voyage_escale
                WHERE id_voyage = :id
                ORDER BY ordre_passage
            ");
            $stmt->execute(['id' => $id]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare("
                UPDATE voyage_escale
                SET ordre_passage = ordre_passage + 10000
                WHERE id_voyage = :id
            ");
            $stmt->execute(['id' => $id]);

            $update = $pdo->prepare("
                UPDATE voyage_escale
                SET ordre_passage = :ordre
                WHERE id_voyage = :id
                  AND id_escale = :escale
            ");

            foreach ($ids as $index => $stopId) {
                $update->execute([
                    'ordre' => $index + 1,
                    'id' => $id,
                    'escale' => $stopId
                ]);
            }

            $pdo->commit();
            self::flash('Escale supprimée.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::flash('Impossible de supprimer cette escale.', 'error');
        }

        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    public static function reorderStops(PDO $pdo): void
    {
        self::canManage();
        self::csrf();

        $id = trim($_POST['id_voyage'] ?? '');
        $order = $_POST['ordre'] ?? [];

        $voyage = self::voyage($pdo, $id);

        if ($voyage['statut'] !== 'PROGRAMME' || !is_array($order)) {
            self::flash('Réorganisation impossible.', 'error');
            self::redirect('/voyages/show?id=' . urlencode($id));
        }

        $order = array_values(array_unique(array_map('strval', $order)));

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id_escale
                FROM voyage_escale
                WHERE id_voyage = :id
            ");
            $stmt->execute(['id' => $id]);

            $existing = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            sort($existing);
            $check = $order;
            sort($check);

            if ($existing !== $check) {
                throw new RuntimeException('Liste des escales invalide.');
            }

            $stmt = $pdo->prepare("
                UPDATE voyage_escale
                SET ordre_passage = ordre_passage + 10000
                WHERE id_voyage = :id
            ");
            $stmt->execute(['id' => $id]);

            $stmt = $pdo->prepare("
                UPDATE voyage_escale
                SET ordre_passage = :ordre
                WHERE id_voyage = :id
                  AND id_escale = :escale
            ");

            foreach ($order as $index => $escale) {
                $stmt->execute([
                    'ordre' => $index + 1,
                    'id' => $id,
                    'escale' => $escale
                ]);
            }

            $pdo->commit();
            self::flash('Ordre des escales mis à jour.');

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::flash('Réorganisation impossible.', 'error');
        }

        self::redirect('/voyages/show?id=' . urlencode($id));
    }

public static function depart(PDO $pdo): void
{
    self::canManage();
    self::csrf();

    $id = trim($_POST['id_voyage'] ?? '');

    $voyage = self::voyage($pdo, $id);

    if ($voyage['statut'] !== 'PROGRAMME') {
        self::flash('Départ impossible pour ce voyage.', 'error');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    try {
        $pdo->beginTransaction();

        // Verrouillage du voyage pendant la vérification
        $stmt = $pdo->prepare("
            SELECT statut
            FROM voyage
            WHERE id_voyage = :id
            FOR UPDATE
        ");

        $stmt->execute(['id' => $id]);

        $statut = $stmt->fetchColumn();

        if ($statut !== 'PROGRAMME') {
            throw new RuntimeException('Le voyage a déjà changé de statut.');
        }

        // Au moins un chauffeur doit être affecté
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM voyage_chauffeur
            WHERE id_voyage = :id
        ");

        $stmt->execute(['id' => $id]);

        if ((int) $stmt->fetchColumn() < 1) {
            throw new RuntimeException(
                'Aucun chauffeur affecté à ce voyage.'
            );
        }

        // Enregistrement du départ réel
        $stmt = $pdo->prepare("
            UPDATE voyage
            SET statut = 'EN COURS',
                heure_depart_reelle = CURTIME()
            WHERE id_voyage = :id
              AND statut = 'PROGRAMME'
        ");

        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Impossible d’enregistrer le départ.');
        }

        $pdo->commit();

        self::flash('Départ enregistré avec succès.');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        self::flash(
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Erreur lors de l’enregistrement du départ.',
            'error'
        );
    }

    self::redirect('/voyages/show?id=' . urlencode($id));
}

    public static function arrive(PDO $pdo): void
    {
        self::canManage();
        self::csrf();

        $id = trim($_POST['id_voyage'] ?? '');
        $voyage = self::voyage($pdo, $id);

        if ($voyage['statut'] !== 'EN COURS') {
            self::flash('Arrivée impossible : voyage non démarré.', 'error');
            self::redirect('/voyages/show?id=' . urlencode($id));
        }

        $stmt = $pdo->prepare("
            UPDATE voyage
            SET statut = 'TERMINE',
                heure_arrivee_reelle = CURTIME()
            WHERE id_voyage = :id
              AND statut = 'EN COURS'
        ");

        $stmt->execute(['id' => $id]);

        self::flash('Arrivée enregistrée.');
        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    public static function cancel(PDO $pdo): void
{
    self::canManage();
    self::csrf();

    $id = trim($_POST['id_voyage'] ?? '');

    $voyage = self::voyage($pdo, $id);

    if ($voyage['statut'] !== 'PROGRAMME') {
        self::flash(
            'Seul un voyage programmé peut être annulé.',
            'error'
        );

        self::redirect('/voyages/show?id=' . urlencode($id));
    }

    try {
        $pdo->beginTransaction();

        // Verrouillage du voyage
        $stmt = $pdo->prepare("
            SELECT statut
            FROM voyage
            WHERE id_voyage = :id
            FOR UPDATE
        ");

        $stmt->execute(['id' => $id]);

        if ($stmt->fetchColumn() !== 'PROGRAMME') {
            throw new RuntimeException(
                'Ce voyage ne peut plus être annulé.'
            );
        }

        // Protection des réservations en attente et confirmées
        if (self::activeReservations($pdo, $id) > 0) {
            throw new RuntimeException(
                'Annulation bloquée : des réservations actives existent. Traitez-les avant toute annulation.'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE voyage
            SET statut = 'ANNULE'
            WHERE id_voyage = :id
              AND statut = 'PROGRAMME'
        ");

        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Impossible d’annuler ce voyage.'
            );
        }

        $pdo->commit();

        self::flash('Voyage annulé avec succès.');

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        self::flash(
            $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Erreur lors de l’annulation du voyage.',
            'error'
        );
    }

    self::redirect('/voyages/show?id=' . urlencode($id));
}
}
