
<?php

final class ClientController
{
    /**
     * Liste et recherche des clients.
     */
    public static function index(PDO $pdo): void
    {
        Access::requirePermission('client.view');

        $search = trim($_GET['q'] ?? '');

        if ($search !== '') {
            $stmt = $pdo->prepare(
                "SELECT * FROM client
                 WHERE nom LIKE :nom
                    OR prenom LIKE :prenom
                    OR telephone LIKE :telephone
                    OR id_client LIKE :id
                 ORDER BY date_creation DESC"
            );

            $term = "%$search%";

            $stmt->execute([
                'nom' => $term,
                'prenom' => $term,
                'telephone' => $term,
                'id' => $term,
            ]);
        } else {
            $stmt = $pdo->query(
                "SELECT * FROM client ORDER BY date_creation DESC"
            );
        }

        $clients = $stmt->fetchAll();

        require dirname(__DIR__, 2) . '/templates/clients/index.php';
    }

    /**
     * Formulaire de création.
     */
    public static function create(): void
    {
        Access::requirePermission('client.create');

        $csrfToken = Csrf::token();

        require dirname(__DIR__, 2) . '/templates/clients/create.php';
    }

    /**
     * Enregistrement d'un client.
     */
    public static function store(PDO $pdo): void
    {
        Access::requirePermission('client.create');
        Request::verifyCsrf();

        $nom = strtoupper(trim($_POST['nom'] ?? ''));
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $typePiece = $_POST['type_piece_identite'] ?? '';
        $numeroPiece = trim($_POST['numero_piece_identite'] ?? '');

        if ($nom === '' || $prenom === '' || $telephone === '') {
            http_response_code(422);
            exit('422 - Nom, prénom et téléphone obligatoires.');
        }

        if (
            strlen($nom) > 100 ||
            strlen($prenom) > 100 ||
            strlen($telephone) > 30 ||
            strlen($numeroPiece) > 50
        ) {
            http_response_code(422);
            exit('422 - Un ou plusieurs champs sont trop longs.');
        }

        $typesAutorises = [
            'CNI',
            'PASSEPORT',
            'PERMIS',
            'CARTE SEJOUR'
        ];

        if (
            $typePiece !== '' &&
            !in_array($typePiece, $typesAutorises, true)
        ) {
            http_response_code(422);
            exit('422 - Type de pièce invalide.');
        }

        if (($typePiece === '') !== ($numeroPiece === '')) {
            http_response_code(422);
            exit('422 - Le type et le numéro de pièce doivent être renseignés ensemble.');
        }

        do {
            $idClient = 'CLI-' . strtoupper(bin2hex(random_bytes(2)));

            $check = $pdo->prepare(
                'SELECT COUNT(*) FROM client WHERE id_client = ?'
            );

            $check->execute([$idClient]);
        } while ((int) $check->fetchColumn() > 0);

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO client (
                    id_client,
                    nom,
                    prenom,
                    telephone,
                    type_piece_identite,
                    numero_piece_identite,
                    date_creation
                ) VALUES (
                    :id,
                    :nom,
                    :prenom,
                    :telephone,
                    :type_piece,
                    :numero_piece,
                    CURDATE()
                )"
            );

            $stmt->execute([
                'id' => $idClient,
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'type_piece' => $typePiece !== '' ? $typePiece : null,
                'numero_piece' => $numeroPiece !== '' ? $numeroPiece : null,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                exit('409 - Identifiant ou pièce d’identité déjà enregistré.');
            }

            throw $e;
        }

        header('Location: /clients');
        exit;
    }

    /**
     * Consulter un client.
     */
    public static function show(PDO $pdo): void
    {
        Access::requirePermission('client.view');

        $id = trim($_GET['id'] ?? '');

        $stmt = $pdo->prepare(
            'SELECT * FROM client WHERE id_client = ?'
        );

        $stmt->execute([$id]);

        $client = $stmt->fetch();

        if (!$client) {
            http_response_code(404);
            exit('404 - Client introuvable.');
        }

        require dirname(__DIR__, 2) . '/templates/clients/show.php';
    }

    /**
     * Formulaire de modification.
     */
    public static function edit(PDO $pdo): void
    {
        Access::requirePermission('client.update');

        $id = trim($_GET['id'] ?? '');

        $stmt = $pdo->prepare(
            'SELECT * FROM client WHERE id_client = ?'
        );

        $stmt->execute([$id]);

        $client = $stmt->fetch();

        if (!$client) {
            http_response_code(404);
            exit('404 - Client introuvable.');
        }

        $csrfToken = Csrf::token();

        require dirname(__DIR__, 2) . '/templates/clients/edit.php';
    }

    /**
     * Enregistrer les modifications.
     */
    public static function update(PDO $pdo): void
    {
        Access::requirePermission('client.update');
        Request::verifyCsrf();

        $id = trim($_POST['id_client'] ?? '');
        $nom = strtoupper(trim($_POST['nom'] ?? ''));
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $typePiece = $_POST['type_piece_identite'] ?? '';
        $numeroPiece = trim($_POST['numero_piece_identite'] ?? '');

        if ($id === '' || $nom === '' || $prenom === '' || $telephone === '') {
            http_response_code(422);
            exit('422 - Informations obligatoires manquantes.');
        }

        if (
            strlen($nom) > 100 ||
            strlen($prenom) > 100 ||
            strlen($telephone) > 30 ||
            strlen($numeroPiece) > 50
        ) {
            http_response_code(422);
            exit('422 - Un ou plusieurs champs sont trop longs.');
        }

        $typesAutorises = [
            'CNI',
            'PASSEPORT',
            'PERMIS',
            'CARTE SEJOUR'
        ];

        if (
            $typePiece !== '' &&
            !in_array($typePiece, $typesAutorises, true)
        ) {
            http_response_code(422);
            exit('422 - Type de pièce invalide.');
        }

        if (($typePiece === '') !== ($numeroPiece === '')) {
            http_response_code(422);
            exit('422 - Le type et le numéro de pièce doivent être renseignés ensemble.');
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE client SET
                    nom = :nom,
                    prenom = :prenom,
                    telephone = :telephone,
                    type_piece_identite = :type_piece,
                    numero_piece_identite = :numero_piece
                 WHERE id_client = :id"
            );

            $stmt->execute([
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'type_piece' => $typePiece !== '' ? $typePiece : null,
                'numero_piece' => $numeroPiece !== '' ? $numeroPiece : null,
                'id' => $id,
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                http_response_code(409);
                exit('409 - Cette pièce d’identité est déjà utilisée.');
            }

            throw $e;
        }

        header('Location: /clients/show?id=' . urlencode($id));
        exit;
    }
}
