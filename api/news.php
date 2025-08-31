<?php
include '../server.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $whereClauses = [];

        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        if (!empty($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $whereClauses[] = "date = '$date'";
        }

        $query = "SELECT * FROM news";
        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $query .= " ORDER BY date DESC";

        $result = $conn->query($query);
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["success" => true, "data" => $data]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'POST':
        $requiredFields = ['title', 'description', 'img', 'link', 'date'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            echo json_encode(["success" => false, "error" => "Missing required fields: " . implode(", ", $missingFields)]);
            break;
        }

        $title = $conn->real_escape_string($input['title']);
        $description = $conn->real_escape_string($input['description']);
        $img = $conn->real_escape_string($input['img']);
        $link = $conn->real_escape_string($input['link']);
        $date = $conn->real_escape_string($input['date']);

        $sql = "INSERT INTO news (title, description, img, link, date) 
                VALUES ('$title', '$description', '$img', '$link', '$date')";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "title" => $title]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'PATCH':
        $whereClauses = [];

        if (!empty($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $whereClauses[] = "date = '$date'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (date/keyword required)"]);
            break;
        }

        $allowedFields = ['title', 'description', 'img', 'link', 'date'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $value = $conn->real_escape_string($value);
                $updates[] = "$key='$value'";
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $sql = "UPDATE news SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "updated_rows" => $conn->affected_rows]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $whereClauses = [];

        if (!empty($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $whereClauses[] = "date = '$date'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (date/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM news WHERE " . implode(" AND ", $whereClauses);

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "deleted_rows" => $conn->affected_rows]);
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