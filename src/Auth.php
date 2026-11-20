<?php

final class Auth
{
    public static function login(PDO $pdo, string $email, string $password): bool
    {
        Session::start();

        // 1. Vérifier les identifiants
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

        if (
            !$employe ||
            !password_verify($password, $employe['mot_de_passe_hash'])
        ) {
            return false;
        }

        // 2. Récupérer le profil de l'employé
        $sql = "
            SELECT
                id_employe,
                nom,
                prenom,
                email,
                id_agence
            FROM employe
            WHERE id_employe = :id_employe
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_employe' => $employe['id_employe']
        ]);

        $profil = $stmt->fetch();

        if (!$profil) {
            return false;
        }

        // 3. Récupérer les rôles et leurs portées
        $sql = "
            SELECT
                r.id_role,
                r.libelle,
                r.portee
            FROM employe_role er
            INNER JOIN role r
                ON r.id_role = er.id_role
            WHERE er.id_employe = :id_employe
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_employe' => $profil['id_employe']
        ]);

        $rolesDetails = $stmt->fetchAll();

        // Un employé sans rôle ne peut pas se connecter
        if (empty($rolesDetails)) {
            return false;
        }

        $roles = array_column($rolesDetails, 'libelle');
        $portees = array_column($rolesDetails, 'portee');

        // 4. Créer la session
        Session::regenerate();

        $_SESSION['employe'] = [
            'id' => $profil['id_employe'],
            'nom' => $profil['nom'],
            'prenom' => $profil['prenom'],
            'email' => $profil['email'],
            'id_agence' => $profil['id_agence'],
            'roles' => $roles,
            'portees' => $portees
        ];

        // 5. Enregistrer la dernière connexion
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

        // Les portées PLATEFORME et ENTREPRISE
        // permettent l'accès à toutes les agences.
        if (
            in_array('PLATEFORME', $user['portees'], true) ||
            in_array('ENTREPRISE', $user['portees'], true)
        ) {
            return true;
        }

        // La portée AGENCE limite l'accès
        // à l'agence de rattachement de l'employé.
        return (
            in_array('AGENCE', $user['portees'], true) &&
            $user['id_agence'] === $idAgence
        );
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }
    }
}
