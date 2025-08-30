<?php
include '../server.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $conn->query("SELECT * FROM iprc WHERE id = $id");
            $data = $result->fetch_assoc();
        } else {
            $result = $conn->query("SELECT * FROM iprc");
            $data = $result->fetch_all(MYSQLI_ASSOC);
        }
        echo json_encode($data);
        break;

    case 'POST':
        $details = $conn->real_escape_string($input['details']);
        $date = $conn->real_escape_string($input['date']);
        $newspaper = $conn->real_escape_string($input['newspaper']);
        $link = $conn->real_escape_string($input['link']);

        $sql = "INSERT INTO iprc (details, date, newspaper, link)
                VALUES ('$details', '$date', '$newspaper', '$link')";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "id" => $conn->insert_id]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'PATCH':
        if (!isset($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "No ID provided"]);
            break;
        }
        $id = intval($_GET['id']);
        $updates = [];
        foreach ($input as $key => $value) {
            $value = $conn->real_escape_string($value);
            $updates[] = "$key='$value'";
        }
        $sql = "UPDATE iprc SET ".implode(", ", $updates)." WHERE id=$id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "No ID provided"]);
            break;
        }
        $id = intval($_GET['id']);
        $sql = "DELETE FROM iprc WHERE id=$id";
        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    default:
        echo json_encode(["success" => false, "error" => "Invalid request"]);
        break;
}

$conn->close();
?>
