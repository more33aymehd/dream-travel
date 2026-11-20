<?php

final class Access
{
    /**
     * Permissions attribuées à chaque rôle.
     * Les permissions de plusieurs rôles se cumulent.
     */
    private const ROLE_PERMISSIONS = [

        'Réceptionniste' => [
            'client.view',
            'client.create',
            'client.update',

            'reservation.view',
            'reservation.create',
            'reservation.cancel.pending',
            'traveller.manage',

            'voyage.view',
            'fare.view',
            'availability.view',

            'payment.collect',

            'cash.session.open.own',
            'cash.session.close.own',
        ],

        'Comptable' => [
            'payment.view',
            'cash.reconciliation.manage',

            'cash.session.open.own',
            'cash.session.close.own',

            'finance.reports.view',
            'finance.reports.export',
            'statistics.commercial.view',
        ],

        "Chef d'agence" => [
            'employee.manage',
            'employee.roles.manage',

            'voyage.manage',
            'vehicle.manage',
            'driver.manage',

            'promotion.manage',

            'client.view',
            'claim.manage',

            'finance.reports.view',
            'cash.session.view',
            'statistics.commercial.view',
        ],

        'Administrateur' => [
            'agency.view',
            'agency.create',
            'agency.update',

            'employee.manage',
            'employee.roles.manage',

            'itinerary.manage',
            'stop.manage',
            'vehicle.manage',
            'driver.manage',

            'client.view',
            'client.create',
            'client.update',
            'client.delete',

            'reservation.view',
            'payment.view',

            'cash.session.open.own',
            'cash.session.close.own',
            'cash.session.open.any',
            'cash.session.close.any',
            'cash.session.view',
            'cash.reconciliation.view',

            'fare.manage',
            'promotion.manage',

            'shipment.view',
            'supplier.view',
            'delivery.view',
            'claim.view',

            'statistics.global.view',
            'finance.reports.view',
            'finance.reports.export',

            'audit.view',
            'audit.export',
        ],
    ];

    /**
     * Exiger une authentification.
     */
    public static function requireAuth(): void
    {
        if (!Auth::check()) {
            http_response_code(401);
            exit('401 - Authentification requise.');
        }
    }

    /**
     * Vérifier une permission en cumulant les rôles.
     */
    public static function hasPermission(string $permission): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        foreach ($user['roles'] as $role) {
            $permissions = self::ROLE_PERMISSIONS[$role] ?? [];

            if (in_array($permission, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exiger une permission précise.
     */
    public static function requirePermission(string $permission): void
    {
        self::requireAuth();

        if (!self::hasPermission($permission)) {
            self::deny();
        }
    }

    /**
     * Exiger au moins une permission.
     */
    public static function requireAnyPermission(array $permissions): void
    {
        self::requireAuth();

        foreach ($permissions as $permission) {
            if (self::hasPermission($permission)) {
                return;
            }
        }

        self::deny();
    }

    /**
     * Exiger une permission et l'accès à une agence.
     */
    public static function requirePermissionForAgency(
        string $permission,
        string $idAgence
    ): void {
        self::requirePermission($permission);
        self::requireAgency($idAgence);
    }

    /**
     * Exiger au moins un des rôles indiqués.
     */
    public static function requireAnyRole(array $roles): void
    {
        self::requireAuth();

        foreach ($roles as $role) {
            if (Auth::hasRole($role)) {
                return;
            }
        }

        self::deny();
    }

    /**
     * Exiger un rôle précis.
     */
    public static function requireRole(string $role): void
    {
        self::requireAnyRole([$role]);
    }

    /**
     * Exiger l'accès à une agence.
     */
    public static function requireAgency(string $idAgence): void
    {
        self::requireAuth();

        if (!Auth::canAccessAgency($idAgence)) {
            self::deny();
        }
    }

    /**
     * Refuser l'accès.
     */
    private static function deny(): void
    {
        http_response_code(403);
        exit('403 - Accès refusé.');
    }
}
