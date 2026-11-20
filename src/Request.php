<?php

final class Request
{
    /**
     * Exiger une méthode HTTP précise.
     */
    public static function requireMethod(string $method): void
    {
        $currentMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

        if ($currentMethod !== strtoupper($method)) {
            header('Allow: ' . strtoupper($method));
            http_response_code(405);
            exit('405 - Méthode HTTP non autorisée.');
        }
    }

    /**
     * Vérifier le jeton CSRF.
     */
    public static function verifyCsrf(): void
    {
        self::requireMethod('POST');

        $token = $_POST['_csrf_token'] ?? null;

        if (!Csrf::verify($token)) {
            http_response_code(403);
            exit('403 - Jeton CSRF invalide.');
        }
    }
}