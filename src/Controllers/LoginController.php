<?php

final class LoginController
{
    public static function show(): void
    {
        if (Auth::check()) {
            header('Location: /');
            exit;
        }

        $csrfToken = Csrf::token();

        require dirname(__DIR__, 2) . '/templates/login.php';
    }

    public static function authenticate(PDO $pdo): void
    {
        Request::verifyCsrf();

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (
            $email === '' ||
            $password === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            http_response_code(422);
            exit('422 - Adresse email ou mot de passe invalide.');
        }

        if (!Auth::login($pdo, $email, $password)) {
            http_response_code(401);
            exit('401 - Identifiants incorrects.');
        }

        Csrf::regenerate();

        header('Location: /');
        exit;
    }
	public static function logout(): void
{
    Request::verifyCsrf();

    Auth::logout();

    header('Location: /login');
    exit;
}
}