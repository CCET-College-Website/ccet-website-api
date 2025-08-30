<?php
include '../server.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = intval($_GET['id']);
            $whereClauses[] = "id = $id";
        }

        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        $query = "SELECT * FROM auditreports";
        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $query .= " ORDER BY id DESC";

        $result = $conn->query($query);
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["success" => true, "data" => $data]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'POST':
        $requiredFields = ['title', 'path'];
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
        $path = $conn->real_escape_string($input['path']);

        $sql = "INSERT INTO auditreports (`title`, `path`) VALUES ('$title', '$path')";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "id" => $conn->insert_id, "title" => $title]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'PATCH':
        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = intval($_GET['id']);
            $whereClauses[] = "id = $id";
        }

        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "Filter required (id or keyword)"]);
            break;
        }

        $allowedFields = ['title', 'path'];
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

        $sql = "UPDATE auditreports SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "updated_rows" => $conn->affected_rows]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = intval($_GET['id']);
            $whereClauses[] = "id = $id";
        }

        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "Filter required (id or keyword)"]);
            break;
        }

        $sql = "DELETE FROM auditreports WHERE " . implode(" AND ", $whereClauses);

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