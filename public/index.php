
<?php


require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::connect();
    $databaseStatus = 'Connecté';
    $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
} catch (PDOException $e) {
    $databaseStatus = 'Déconnecté';
    $databaseName = 'Indisponible';
    error_log($e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dream Travel</title>
</head>
<body>
    <h1>Dream Travel</h1>
    <p>Plateforme de gestion de transport et de voyages.</p>

    <h2>État du système</h2>
    <p>Base de données : <?= htmlspecialchars($databaseStatus) ?></p>
    <p>Base utilisée : <?= htmlspecialchars($databaseName) ?></p>
</body>
</html>