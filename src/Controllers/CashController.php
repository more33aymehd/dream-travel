<?php

final class CashController
{
    private static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    private static function hasOverviewPermission(): bool
    {
        return Access::hasPermission('cash.session.view')
            || Access::hasPermission('payment.view')
            || Access::hasPermission('cash.reconciliation.manage')
            || Access::hasPermission('cash.reconciliation.view');
    }

    private static function canViewPayments(): bool
    {
        return Access::hasPermission('payment.view')
            || Access::hasPermission('cash.reconciliation.manage')
            || Access::hasPermission('cash.reconciliation.view');
    }

    private static function findSession(PDO $pdo, string $id): array
    {
        $stmt = $pdo->prepare(
            "SELECT
                s.id_session,
                s.id_agence,
                s.id_employe,
                s.date_heure_ouverture,
                s.date_heure_fermeture,
                s.total_encaisse_fcfa,
                s.statut_session,
                e.nom,
                e.prenom
             FROM session_de_caisse s
             JOIN employe e ON e.id_employe = s.id_employe
             WHERE s.id_session = :id"
        );

        $stmt->execute(['id' => $id]);
        $session = $stmt->fetch();

        if (!$session) {
            http_response_code(404);
            exit('404 - Session de caisse introuvable.');
        }

        Access::requireAgency($session['id_agence']);

        return $session;
    }

    public static function index(PDO $pdo): void
    {
        Access::requireAnyPermission([
            'cash.session.open.own',
            'cash.session.close.own',
            'cash.session.view',
            'payment.view',
            'cash.reconciliation.manage',
            'cash.reconciliation.view',
        ]);

        $user = Auth::user();
        $canViewAll = self::hasOverviewPermission();

        $stmt = $pdo->query(
            "SELECT
                s.id_session,
                s.id_agence,
                s.id_employe,
                s.date_heure_ouverture,
                s.date_heure_fermeture,
                s.total_encaisse_fcfa,
                s.statut_session,
                e.nom,
                e.prenom
             FROM session_de_caisse s
             JOIN employe e ON e.id_employe = s.id_employe
             ORDER BY s.date_heure_ouverture DESC"
        );

        $sessions = array_values(array_filter(
            $stmt->fetchAll(),
            function (array $row) use ($user, $canViewAll): bool {
                if (!Auth::canAccessAgency($row['id_agence'])) {
                    return false;
                }

                return $canViewAll
                    || $row['id_employe'] === $user['id'];
            }
        ));

        $currentSession = null;

        foreach ($sessions as $session) {
            if (
                $session['id_employe'] === $user['id']
                && $session['statut_session'] === 'OUVERTE'
            ) {
                $currentSession = $session;
                break;
            }
        }

        $csrfToken = Csrf::token();
        $canOpen = Access::hasPermission('cash.session.open.own');
        $canViewPayments = self::canViewPayments();

        require dirname(__DIR__, 2) . '/templates/cash/index.php';
    }

    public static function open(PDO $pdo): void
    {
        Access::requirePermission('cash.session.open.own');
        Request::verifyCsrf();

        $user = Auth::user();

        try {
            $stmt = $pdo->prepare(
                "CALL sp_ouvrir_session(:employe, @id_session)"
            );

            $stmt->execute([
                'employe' => $user['id'],
            ]);

            $stmt->closeCursor();

            $idSession = $pdo->query(
                "SELECT @id_session"
            )->fetchColumn();

            if (!$idSession) {
                throw new RuntimeException(
                    'La session de caisse n’a pas été créée.'
                );
            }

            self::redirect(
                '/cash/show?id=' . rawurlencode($idSession)
                . '&status=opened'
            );
        } catch (Throwable $e) {
            error_log('Ouverture caisse : ' . $e->getMessage());

            self::redirect('/cash?status=open_error');
        }
    }

    public static function show(PDO $pdo): void
    {
        Access::requireAnyPermission([
            'cash.session.open.own',
            'cash.session.close.own',
            'cash.session.view',
            'payment.view',
            'cash.reconciliation.manage',
            'cash.reconciliation.view',
        ]);

        $id = trim($_GET['id'] ?? '');

        if ($id === '') {
            self::redirect('/cash');
        }

        $session = self::findSession($pdo, $id);
        $user = Auth::user();

        if (
            !self::hasOverviewPermission()
            && $session['id_employe'] !== $user['id']
        ) {
            http_response_code(403);
            exit('403 - Vous ne pouvez consulter que vos propres sessions.');
        }

        $canClose = (
            Access::hasPermission('cash.session.close.own')
            && $session['id_employe'] === $user['id']
            && $session['statut_session'] === 'OUVERTE'
        );

        $canViewPayments = self::canViewPayments();
        $payments = [];

        if ($canViewPayments) {
            $stmt = $pdo->prepare(
                "SELECT
                    id_paiement,
                    id_reservation,
                    montant_fcfa,
                    moyen_paiement,
                    date_heure_paiement,
                    statut_paiement
                 FROM paiement
                 WHERE id_session = :id_session
                 ORDER BY date_heure_paiement DESC"
            );

            $stmt->execute([
                'id_session' => $id,
            ]);

            $payments = $stmt->fetchAll();
        }

        $csrfToken = Csrf::token();
        $status = $_GET['status'] ?? '';

        require dirname(__DIR__, 2) . '/templates/cash/show.php';
    }

    public static function close(PDO $pdo): void
    {
        Access::requirePermission('cash.session.close.own');
        Request::verifyCsrf();

        $id = trim($_POST['id_session'] ?? '');

        if ($id === '') {
            self::redirect('/cash?status=missing_session');
        }

        $user = Auth::user();

        try {
            $session = self::findSession($pdo, $id);

            if ($session['id_employe'] !== $user['id']) {
                http_response_code(403);
                exit('403 - Seul le propriétaire peut fermer cette session.');
            }

            if ($session['statut_session'] !== 'OUVERTE') {
                self::redirect(
                    '/cash/show?id=' . rawurlencode($id)
                    . '&status=already_closed'
                );
            }

            $stmt = $pdo->prepare(
                "CALL sp_fermer_session(:session, :employe)"
            );

            $stmt->execute([
                'session' => $id,
                'employe' => $user['id'],
            ]);

            $stmt->closeCursor();

            self::redirect(
                '/cash/show?id=' . rawurlencode($id)
                . '&status=closed'
            );
        } catch (Throwable $e) {
            error_log('Fermeture caisse : ' . $e->getMessage());

            self::redirect(
                '/cash/show?id=' . rawurlencode($id)
                . '&status=close_error'
            );
        }
    }
}
