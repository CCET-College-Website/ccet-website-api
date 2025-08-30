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
            $whereClauses[] = "`date-issued` = '$date'";
        }

        if (!empty($_GET['type'])) {
            $type = $conn->real_escape_string($_GET['type']);
            $whereClauses[] = "`type` = '$type'";
        }

        $query = "SELECT * FROM forms";
        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $query .= " ORDER BY `date-issued` DESC";

        $result = $conn->query($query);
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["success" => true, "data" => $data]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'POST':
        $requiredFields = ['title', 'link', 'type', 'date-issued'];
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
        $link = $conn->real_escape_string($input['link']);
        $type = $conn->real_escape_string($input['type']);
        $dateIssued = $conn->real_escape_string($input['date-issued']);

        $sql = "INSERT INTO forms (`title`, `link`, `type`, `date-issued`) 
                VALUES ('$title', '$link', '$type', '$dateIssued')";

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
            $whereClauses[] = "`date-issued` = '$date'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }
        if (!empty($_GET['type'])) {
            $type = $conn->real_escape_string($_GET['type']);
            $whereClauses[] = "`type` = '$type'";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (date/keyword/type required)"]);
            break;
        }

        $allowedFields = ['title', 'link', 'type', 'date-issued'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $value = $conn->real_escape_string($value);
                $updates[] = "`$key`='$value'";
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $sql = "UPDATE forms SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
            $whereClauses[] = "`date-issued` = '$date'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }
        if (!empty($_GET['type'])) {
            $type = $conn->real_escape_string($_GET['type']);
            $whereClauses[] = "`type` = '$type'";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (date/keyword/type required)"]);
            break;
        }

        $sql = "DELETE FROM forms WHERE " . implode(" AND ", $whereClauses);

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