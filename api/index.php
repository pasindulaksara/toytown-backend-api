<?php

// CORS (local dev)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/../config/db.php";

// Core
require_once __DIR__ . "/../src/Core/Router.php";
require_once __DIR__ . "/../src/Core/Request.php";
require_once __DIR__ . "/../src/Core/Response.php";
require_once __DIR__ . "/../src/Utils/Validators.php";
require_once __DIR__ . "/../src/Repositories/ParentsRepository.php";
require_once __DIR__ . "/../src/Repositories/SessionsRepository.php";

// Controllers
require_once __DIR__ . "/../src/Controllers/ParentsController.php";
require_once __DIR__ . "/../src/Controllers/SessionsController.php";
// Init router
$router = new Router();

// Health check
$router->add("GET", "", function ($params = []) {
    Response::json(["ok" => true, "message" => "ToyTown API running"]);
});

// Parents
$router->add("GET", "parents", function ($params = []) {
    ParentsController::index();
});

$router->add("POST", "parents", function ($params = []) {
    ParentsController::store();
});

$router->add("GET", "parents/{id}", function ($params) {
    ParentsController::show((int)$params["id"]);
});


//sessions
$router->add("POST", "sessions/start", function ($params = []) {
    SessionsController::start();
});

$router->add("GET", "sessions", function ($params = []) {
    SessionsController::index();
});

$router->add("POST", "sessions/{id}/end", function ($params) {
    SessionsController::end((int)$params["id"]);
});


// Dispatch
$router->dispatch(Request::method(), Request::path());