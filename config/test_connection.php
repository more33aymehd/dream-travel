
<?php

require_once __DIR__ . '/config/database.php';

try {
    $pdo = Database::connect();

    echo "Connexion à MySQL réussie !<br>";

    $stmt = $pdo->query("SELECT DATABASE() AS nom_base, VERSION() AS version_mysql");
    $result = $stmt->fetch();

    echo "Base : " . htmlspecialchars($result['nom_base']) . "<br>";
    echo "MySQL : " . htmlspecialchars($result['version_mysql']);

} catch (PDOException $e) {
    http_response_code(500);
    echo "Échec de connexion à la base de données.";
    error_log($e->getMessage());
}
