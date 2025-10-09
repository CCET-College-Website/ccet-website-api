<?php
include '../server.php'; // assumes $conn = new mysqli(...);

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {

    case 'GET':
        $conditions = [];

        if (isset($_GET['title'])) {
            $title = $conn->real_escape_string($_GET['title']);
            $conditions[] = "title LIKE '%$title%'";
        }
        if (isset($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $conditions[] = "date = '$date'";
        }

        $where = count($conditions) > 0 ? "WHERE " . implode(" AND ", $conditions) : "";
        $sql = "SELECT * FROM tenders $where";
        $result = $conn->query($sql);

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode($rows);
        break;


    case 'POST':
        $title = $conn->real_escape_string($input['title']);
        $link = $conn->real_escape_string($input['link']);
        $date = $conn->real_escape_string($input['date']);

        $sql = "INSERT INTO tenders (title, link, date) 
                VALUES ('$title', '$link', '$date')";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;


    case 'PATCH':
        if (!isset($_GET['title'])) {
            echo json_encode(["success" => false, "error" => "Title is required for update"]);
            break;
        }

        $titleKey = $conn->real_escape_string($_GET['title']);
        $updates = [];
        foreach ($input as $key => $value) {
            $updates[] = "$key = '" . $conn->real_escape_string($value) . "'";
        }
        $sql = "UPDATE tenders SET " . implode(", ", $updates) . " WHERE title = '$titleKey'";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;


    case 'DELETE':
        if (!isset($_GET['title'])) {
            echo json_encode(["success" => false, "error" => "Title is required for delete"]);
            break;
        }

        $titleKey = $conn->real_escape_string($_GET['title']);
        $sql = "DELETE FROM tenders WHERE title = '$titleKey'";

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
