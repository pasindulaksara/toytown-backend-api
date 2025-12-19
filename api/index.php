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

// Controllers
require_once __DIR__ . "/../src/Controllers/ParentsController.php";

// Init router
$router = new Router();

// Health check
$router->add("GET", "", function () {
    Response::json(["ok" => true, "message" => "ToyTown API running"]);
});

// Parents
$router->add("GET", "parents", [ParentsController::class, "index"]);
$router->add("POST", "parents", [ParentsController::class, "store"]);

// Dispatch
$router->dispatch(Request::method(), Request::path());