<?php

require_once __DIR__ . '/../config/bootstrap.php';

$pdo = Database::connect();

$router = new Router();

$router->get('/', function () use ($pdo) {
    if (!Auth::check()) {
        header('Location: /login');
        exit;
    }

    HomeController::index($pdo);
});
$router->get('/login', function () {
    LoginController::show();
});

$router->post('/login', function () use ($pdo) {
    LoginController::authenticate($pdo);
});
$router->post('/logout', function () {
    LoginController::logout();
});
$router->get('/clients', function () use ($pdo) {
    ClientController::index($pdo);
});

$router->get('/clients/create', function () {
    ClientController::create();
});

$router->post('/clients/store', function () use ($pdo) {
    ClientController::store($pdo);
});

$router->get('/clients/show', function () use ($pdo) {
    ClientController::show($pdo);
});

$router->get('/clients/edit', function () use ($pdo) {
    ClientController::edit($pdo);
});

$router->post('/clients/update', function () use ($pdo) {
    ClientController::update($pdo);
});

// Module Réservations
$router->get('/reservations', function () use ($pdo) {
    ReservationController::index($pdo);
});

$router->get('/voyages', function () use ($pdo) {
    VoyageController::index($pdo);
});

// Module Gestion des voyages

$router->get('/voyages/show', function () use ($pdo) {
    VoyageController::show($pdo);
});

$router->get('/voyages/create', function () use ($pdo) {
    VoyageController::create($pdo);
});

$router->post('/voyages/store', function () use ($pdo) {
    VoyageController::store($pdo);
});

$router->get('/voyages/edit', function () use ($pdo) {
    VoyageController::edit($pdo);
});

$router->post('/voyages/update', function () use ($pdo) {
    VoyageController::update($pdo);
});

// Affectation des chauffeurs
$router->post('/voyages/driver/add', function () use ($pdo) {
    VoyageController::addDriver($pdo);
});

$router->post('/voyages/driver/remove', function () use ($pdo) {
    VoyageController::removeDriver($pdo);
});

// Gestion des escales
$router->post('/voyages/stop/add', function () use ($pdo) {
    VoyageController::addStop($pdo);
});

$router->post('/voyages/stop/remove', function () use ($pdo) {
    VoyageController::removeStop($pdo);
});

$router->post('/voyages/stop/reorder', function () use ($pdo) {
    VoyageController::reorderStops($pdo);
});

// Suivi du voyage
$router->post('/voyages/depart', function () use ($pdo) {
    VoyageController::depart($pdo);
});

$router->post('/voyages/arrive', function () use ($pdo) {
    VoyageController::arrive($pdo);
});

$router->post('/voyages/cancel', function () use ($pdo) {
    VoyageController::cancel($pdo);
});
$router->get('/reservations/create', function () use ($pdo) {
    ReservationController::create($pdo);
});

$router->post('/reservations/store', function () use ($pdo) {
    ReservationController::store($pdo);
});

$router->get('/reservations/show', function () use ($pdo) {
    ReservationController::show($pdo);
});

$router->post('/reservations/passenger', function () use ($pdo) {
    ReservationController::addPassenger($pdo);
});

$router->post('/reservations/pay', function () use ($pdo) {
    ReservationController::pay($pdo);
});

$router->post('/reservations/cancel', function () use ($pdo) {
    ReservationController::cancel($pdo);
});
// Module Gestion des caisses
$router->get('/cash', function () use ($pdo) {
    CashController::index($pdo);
});

$router->post('/cash/open', function () use ($pdo) {
    CashController::open($pdo);
});

$router->get('/cash/show', function () use ($pdo) {
    CashController::show($pdo);
});

$router->post('/cash/close', function () use ($pdo) {
    CashController::close($pdo);
});
$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/'
);