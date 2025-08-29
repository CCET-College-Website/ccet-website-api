<?php
include '../server.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        break;

    case 'POST':
        break;

    case 'PATCH':
        break;

    case 'DELETE':
        break;

    default:
        break;
}

$conn->close();
?>