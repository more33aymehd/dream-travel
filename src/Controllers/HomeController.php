<?php

final class HomeController
{
    public static function index(PDO $pdo): void
    {
        $user = Auth::user();
		$csrfToken = Csrf::token();
        try {
            $databaseStatus = 'Connecté';
            $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (PDOException $e) {
            $databaseStatus = 'Déconnecté';
            $databaseName = 'Indisponible';
            error_log($e->getMessage());
        }

        require dirname(__DIR__, 2) . '/templates/home.php';
    }
}