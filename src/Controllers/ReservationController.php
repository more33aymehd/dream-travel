
<?php

final class ReservationController
{
    private static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private static function showUrl(string $id, string $status): string
    {
        return '/reservations/show?id=' . rawurlencode($id)
            . '&status=' . rawurlencode($status);
    }

    private static function verifyReservationAgency(PDO $pdo, string $id): array
    {
        $stmt = $pdo->prepare(
            "SELECT r.id_reservation, r.id_employe, e.id_agence
             FROM reservation r
             JOIN employe e ON e.id_employe = r.id_employe
             WHERE r.id_reservation = :id"
        );
        $stmt->execute(['id' => $id]);
        $reservation = $stmt->fetch();

        if (!$reservation) {
            http_response_code(404);
            exit('404 - Réservation introuvable.');
        }

        Access::requireAgency($reservation['id_agence']);

        return $reservation;
    }

    public static function index(PDO $pdo): void
    {
        Access::requirePermission('reservation.view');

        $search = trim($_GET['q'] ?? '');

        $sql = "
            SELECT
                r.id_reservation,
                r.date_heure_reservation,
                r.date_expiration,
                r.montant_total_fcfa,
                r.statut,
                c.nom AS client_nom,
                c.prenom AS client_prenom,
                v.itineraire,
                v.date_voyage,
                v.heure_depart_prevue,
                e.id_agence
            FROM reservation r
            JOIN client c ON c.id_client = r.id_client
            JOIN v_voyage_detail v ON v.id_voyage = r.id_voyage
            JOIN employe e ON e.id_employe = r.id_employe
        ";

        $params = [];

        if ($search !== '') {
            $sql .= "
                WHERE (
                    r.id_reservation LIKE :id
                    OR c.nom LIKE :nom
                    OR c.prenom LIKE :prenom
                    OR c.telephone LIKE :telephone
                )
            ";

            $term = '%' . $search . '%';

            $params = [
                'id' => $term,
                'nom' => $term,
                'prenom' => $term,
                'telephone' => $term,
            ];
        }

        $sql .= " ORDER BY r.date_heure_reservation DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $reservations = array_values(array_filter(
            $stmt->fetchAll(),
            fn(array $row): bool => Auth::canAccessAgency($row['id_agence'])
        ));

        $csrfToken = Csrf::token();

        require dirname(__DIR__, 2) . '/templates/reservations/index.php';
    }

    public static function create(PDO $pdo): void
    {
        Access::requirePermission('reservation.create');

        $clients = $pdo->query(
            "SELECT id_client, nom, prenom, telephone
             FROM client
             ORDER BY nom, prenom"
        )->fetchAll();

        $voyages = $pdo->query(
            "SELECT
                p.id_voyage,
                p.itineraire,
                p.date_voyage,
                p.heure_depart_prevue,
                p.immatriculation,
                p.classe_voyage,
                p.capacite_totale,
                p.places_libres
             FROM v_places_voyage p
             WHERE p.statut = 'PROGRAMME'
               AND TIMESTAMP(p.date_voyage, p.heure_depart_prevue) > NOW()
               AND p.places_libres > 0
             ORDER BY p.date_voyage, p.heure_depart_prevue"
        )->fetchAll();

        $csrfToken = Csrf::token();

        require dirname(__DIR__, 2) . '/templates/reservations/create.php';
    }

    public static function store(PDO $pdo): void
    {
        Access::requirePermission('reservation.create');
        Request::verifyCsrf();

        $idClient = trim($_POST['id_client'] ?? '');
        $idVoyage = trim($_POST['id_voyage'] ?? '');

        if ($idClient === '' || $idVoyage === '') {
            self::redirect('/reservations/create?status=missing_fields');
        }

        $user = Auth::user();

        try {
            $stmt = $pdo->prepare(
                "CALL sp_creer_reservation(
                    :client, :voyage, :employe, :duree, @id_reservation
                )"
            );

            $stmt->execute([
                'client' => $idClient,
                'voyage' => $idVoyage,
                'employe' => $user['id'],
                'duree' => 60,
            ]);

            $stmt->closeCursor();

            $idReservation = $pdo->query(
                "SELECT @id_reservation"
            )->fetchColumn();

            if (!$idReservation) {
                throw new RuntimeException('La réservation n’a pas été créée.');
            }

            self::redirect(self::showUrl($idReservation, 'created'));
        } catch (Throwable $e) {
            error_log('Création réservation : ' . $e->getMessage());
            self::redirect('/reservations/create?status=create_error');
        }
    }

    public static function show(PDO $pdo): void
    {
        Access::requirePermission('reservation.view');

        $id = trim($_GET['id'] ?? '');

        if ($id === '') {
            http_response_code(400);
            exit('400 - Identifiant de réservation manquant.');
        }

        self::verifyReservationAgency($pdo, $id);

        $stmt = $pdo->prepare(
            "SELECT
                r.*,
                c.nom AS client_nom,
                c.prenom AS client_prenom,
                c.telephone AS client_telephone,
                c.type_piece_identite AS client_type_piece,
                c.numero_piece_identite AS client_numero_piece,
                v.itineraire,
                v.date_voyage,
                v.heure_depart_prevue,
                v.heure_arrivee_prevue,
                v.immatriculation,
                v.marque,
                v.modele,
                e.id_agence
             FROM reservation r
             JOIN client c ON c.id_client = r.id_client
             JOIN v_voyage_detail v ON v.id_voyage = r.id_voyage
             JOIN employe e ON e.id_employe = r.id_employe
             WHERE r.id_reservation = :id"
        );
        $stmt->execute(['id' => $id]);
        $reservation = $stmt->fetch();

        if (!$reservation) {
            http_response_code(404);
            exit('404 - Réservation introuvable.');
        }

        $stmt = $pdo->prepare(
            "SELECT *
             FROM reservation_voyageur
             WHERE id_reservation = :id
             ORDER BY numero_voyageur"
        );
        $stmt->execute(['id' => $id]);
        $voyageurs = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            "SELECT
                b.id_billet,
                b.numero_voyageur,
                b.numero_place,
                b.montant_fcfa,
                b.date_emission,
                p.id_paiement,
                p.moyen_paiement,
                p.statut_paiement
             FROM billet b
             JOIN paiement p ON p.id_paiement = b.id_paiement
             WHERE b.id_reservation = :id
             ORDER BY b.numero_voyageur"
        );
        $stmt->execute(['id' => $id]);
        $billets = $stmt->fetchAll();

        $sessions = [];
        $promotions = [];

        if (Access::hasPermission('payment.collect')) {
            $stmt = $pdo->prepare(
                "SELECT id_session, id_agence, date_heure_ouverture
                 FROM session_de_caisse
                 WHERE statut_session = 'OUVERTE'
                   AND id_agence = :agence
                 ORDER BY date_heure_ouverture DESC"
            );
            $stmt->execute(['agence' => $reservation['id_agence']]);
            $sessions = $stmt->fetchAll();

            $promotions = $pdo->query(
                "SELECT id_promotion, mode_remise, remise
                 FROM promotion
                 WHERE CURDATE() BETWEEN date_debut AND date_fin
                 ORDER BY id_promotion"
            )->fetchAll();
        }

        $csrfToken = Csrf::token();
        $status = $_GET['status'] ?? '';

        require dirname(__DIR__, 2) . '/templates/reservations/show.php';
    }

    public static function addPassenger(PDO $pdo): void
    {
        Access::requirePermission('reservation.create');
        Request::verifyCsrf();

        $id = trim($_POST['id_reservation'] ?? '');
        self::verifyReservationAgency($pdo, $id);

        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $typePiece = trim($_POST['type_piece'] ?? '');
        $numeroPiece = trim($_POST['numero_piece'] ?? '');
        $numeroPlace = trim($_POST['numero_place'] ?? '');
        $categorie = trim($_POST['categorie_tarif'] ?? '');

        $typesValides = ['', 'CNI', 'PASSEPORT', 'PERMIS', 'CARTE SEJOUR'];
        $categoriesValides = ['STANDARD', 'VIP', 'ETUDIANT'];

        if (
            $nom === '' ||
            $prenom === '' ||
            strlen($nom) > 100 ||
            strlen($prenom) > 100 ||
            !in_array($typePiece, $typesValides, true) ||
            !in_array($categorie, $categoriesValides, true)
        ) {
            self::redirect(self::showUrl($id, 'invalid_passenger'));
        }

        if (($typePiece === '') !== ($numeroPiece === '')) {
            self::redirect(self::showUrl($id, 'invalid_identity'));
        }

        if (strlen($numeroPiece) > 50) {
            self::redirect(self::showUrl($id, 'invalid_identity'));
        }

        $place = null;

        if ($numeroPlace !== '') {
            if (!ctype_digit($numeroPlace) || (int) $numeroPlace < 1) {
                self::redirect(self::showUrl($id, 'invalid_seat'));
            }
            $place = (int) $numeroPlace;
        }

        try {
            $stmt = $pdo->prepare(
                "CALL sp_ajouter_voyageur(
                    :reservation,
                    :nom,
                    :prenom,
                    :type_piece,
                    :numero_piece,
                    :place,
                    :categorie,
                    @numero_voyageur
                )"
            );

            $stmt->bindValue(':reservation', $id);
            $stmt->bindValue(':nom', $nom);
            $stmt->bindValue(':prenom', $prenom);
            $stmt->bindValue(':type_piece', $typePiece === '' ? null : $typePiece);
            $stmt->bindValue(':numero_piece', $numeroPiece === '' ? null : $numeroPiece);
            $stmt->bindValue(':place', $place, $place === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':categorie', $categorie);
            $stmt->execute();
            $stmt->closeCursor();

            self::redirect(self::showUrl($id, 'passenger_added'));
        } catch (Throwable $e) {
            error_log('Ajout voyageur : ' . $e->getMessage());
            self::redirect(self::showUrl($id, 'passenger_error'));
        }
    }

    public static function pay(PDO $pdo): void
    {
        Access::requirePermission('payment.collect');
        Request::verifyCsrf();

        $id = trim($_POST['id_reservation'] ?? '');
        $reservationAccess = self::verifyReservationAgency($pdo, $id);

        $idSession = trim($_POST['id_session'] ?? '');
        $idPromotion = trim($_POST['id_promotion'] ?? '');
        $moyen = trim($_POST['moyen_paiement'] ?? '');

        $moyensValides = ['ESPECES', 'MOBILE MONEY', 'ORANGE MONEY'];

        if (
            $idSession === '' ||
            !in_array($moyen, $moyensValides, true)
        ) {
            self::redirect(self::showUrl($id, 'invalid_payment'));
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT id_session
                 FROM session_de_caisse
                 WHERE id_session = :session
                   AND id_agence = :agence
                   AND statut_session = 'OUVERTE'"
            );
            $stmt->execute([
                'session' => $idSession,
                'agence' => $reservationAccess['id_agence'],
            ]);

            if (!$stmt->fetch()) {
                self::redirect(self::showUrl($id, 'invalid_cash_session'));
            }

            $stmt = $pdo->prepare(
                "CALL sp_enregistrer_paiement(
                    :reservation,
                    :session,
                    :moyen,
                    :promotion,
                    @id_paiement
                )"
            );

            $stmt->bindValue(':reservation', $id);
            $stmt->bindValue(':session', $idSession);
            $stmt->bindValue(':moyen', $moyen);
            $stmt->bindValue(':promotion', $idPromotion === '' ? null : $idPromotion);
            $stmt->execute();
            $stmt->closeCursor();

            $idPaiement = $pdo->query(
                "SELECT @id_paiement"
            )->fetchColumn();

            if (!$idPaiement) {
                throw new RuntimeException('Le paiement n’a pas été enregistré.');
            }

            self::redirect(self::showUrl($id, 'paid'));
        } catch (Throwable $e) {
            error_log('Paiement réservation : ' . $e->getMessage());
            self::redirect(self::showUrl($id, 'payment_error'));
        }
    }

    public static function cancel(PDO $pdo): void
    {
        Access::requirePermission('reservation.cancel.pending');
        Request::verifyCsrf();

        $id = trim($_POST['id_reservation'] ?? '');
        self::verifyReservationAgency($pdo, $id);

        try {
            $stmt = $pdo->prepare(
                "CALL sp_annuler_reservation(:id)"
            );
            $stmt->execute(['id' => $id]);
            $stmt->closeCursor();

            self::redirect(self::showUrl($id, 'cancelled'));
        } catch (Throwable $e) {
            error_log('Annulation réservation : ' . $e->getMessage());
            self::redirect(self::showUrl($id, 'cancel_error'));
        }
    }
}
