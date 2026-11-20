<?php

// Configuration et connexion à la base de données
require_once __DIR__ . '/database.php';

// Gestion de session et sécurité
require_once dirname(__DIR__) . '/src/Session.php';
require_once dirname(__DIR__) . '/src/Csrf.php';
require_once dirname(__DIR__) . '/src/Request.php';
require_once dirname(__DIR__) . '/src/Router.php';
// Authentification
require_once dirname(__DIR__) . '/src/Auth.php';

// Contrôle des accès et des autorisations
require_once dirname(__DIR__) . '/src/Access.php';
require_once dirname(__DIR__) . '/src/Controllers/HomeController.php';
require_once dirname(__DIR__) . '/src/Controllers/LoginController.php';
require_once dirname(__DIR__) . '/src/Controllers/ClientController.php';
require_once dirname(__DIR__) . '/src/Controllers/ReservationController.php';
require_once dirname(__DIR__) . '/src/Controllers/CashController.php';
require_once dirname(__DIR__) . '/src/Controllers/VoyageController.php';
// Démarrage de la session
Session::start();
