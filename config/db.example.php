<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

$host = "localhost";
$user = "DATABASE_USERNAME";
$pass = "DATABASE_PW";
$db   = "DATABASE_NAME";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed.']));
}

$conn->set_charset("utf8mb4");
?>