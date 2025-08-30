<?php
include '../server.php'; // Make sure this has $conn = new mysqli(...);

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $sql = "SELECT * FROM responsibilities WHERE id = $id";
            $result = $conn->query($sql);
            echo json_encode($result->fetch_assoc());
        } else {
            $sql = "SELECT * FROM responsibilities";
            $result = $conn->query($sql);
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            echo json_encode($rows);
        }
        break;
        
    case 'POST':
        $name = $conn->real_escape_string($input['name']);
        $img = $conn->real_escape_string($input['img']);
        $proff_inc = $conn->real_escape_string($input['proff_inc']);
        $dept = $conn->real_escape_string($input['dept']);
        $number = $conn->real_escape_string($input['number']);
        $email = $conn->real_escape_string($input['email']);

        $sql = "INSERT INTO responsibilities (name, img, proff_inc, dept, number, email) 
                VALUES ('$name', '$img', '$proff_inc', '$dept', '$number', '$email')";
        
        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "id" => $conn->insert_id]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'PATCH':
        if (!isset($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID is required"]);
            break;
        }

        $id = intval($_GET['id']);
        $updates = [];
        foreach ($input as $key => $value) {
            $updates[] = "$key = '" . $conn->real_escape_string($value) . "'";
        }
        $sql = "UPDATE responsibilities SET " . implode(", ", $updates) . " WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;


    case 'DELETE':
        if (!isset($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID is required"]);
            break;
        }

        $id = intval($_GET['id']);
        $sql = "DELETE FROM responsibilities WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    default:
        echo json_encode(["success" => false, "error" => "Invalid request method"]);
        break;
}

$conn->close();
?>
