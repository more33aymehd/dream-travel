
<?php

final class Auth
{
    public static function login(PDO $pdo, string $email, string $password): bool
    {
		Session::start();
        $sql = "
            SELECT
                id_employe,
                mot_de_passe_hash
            FROM employe
            WHERE email = :email
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'email' => trim($email)
        ]);

        $employe = $stmt->fetch();

        if (!$employe || !password_verify($password, $employe['mot_de_passe_hash'])) {
            return false;
        }

        $sql = "
            SELECT
                id_employe,
                nom,
                prenom,
                email,
                id_agence,
                roles
            FROM v_employe_roles
            WHERE id_employe = :id_employe
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_employe' => $employe['id_employe']
        ]);

        $profil = $stmt->fetch();

        if (!$profil || empty($profil['roles'])) {
            return false;
        }

        $roles = array_map(
            'trim',
            explode(',', $profil['roles'])
        );

        if (empty($roles)) {
            return false;
        }

        Session::regenerate();

        $_SESSION['employe'] = [
            'id' => $profil['id_employe'],
            'nom' => $profil['nom'],
            'prenom' => $profil['prenom'],
            'email' => $profil['email'],
            'id_agence' => $profil['id_agence'],
            'roles' => $roles
        ];

        $update = $pdo->prepare("
            UPDATE employe
            SET derniere_connexion = NOW()
            WHERE id_employe = :id_employe
        ");

        $update->execute([
            'id_employe' => $profil['id_employe']
        ]);

        return true;
    }


    public static function check(): bool
    {
        return isset($_SESSION['employe']['id']);
    }

    public static function user(): ?array
    {
        return self::check() ? $_SESSION['employe'] : null;
    }

    public static function hasRole(string $role): bool
    {
        if (!self::check()) {
            return false;
        }

        return in_array(
            $role,
            $_SESSION['employe']['roles'],
            true
        );
    }

    public static function canAccessAgency(string $idAgence): bool
    {
        if (!self::check()) {
            return false;
        }

        $user = self::user();

        // L'employé ne peut accéder qu'à sa propre agence.
        return $user['id_agence'] === $idAgence;
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }
    }

}
