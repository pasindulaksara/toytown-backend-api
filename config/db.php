<?php

function db() {
  $host = "localhost";
  $dbname = "toytown";
  $user = "root";
  $pass = ""; // leave empty for WAMP default

  try {
    $pdo = new PDO(
      "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
      $user,
      $pass,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
    return $pdo;
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
      "error" => "DB connection failed",
      "message" => $e->getMessage()
    ]);
    exit;
  }
}