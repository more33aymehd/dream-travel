
<?php

final class Database
{
    private static function loadEnv(): void
    {
        $envFile = dirname(__DIR__) . '/.env';

        if (!file_exists($envFile)) {
            throw new RuntimeException('Fichier .env introuvable.');
        }

        $variables = parse_ini_file($envFile);

        if ($variables === false) {
            throw new RuntimeException('Impossible de lire le fichier .env.');
        }

        foreach ($variables as $key => $value) {
            $_ENV[$key] = $value;
        }
    }

    public static function connect(): PDO
    {
        self::loadEnv();

        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'];
        $name = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
