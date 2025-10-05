<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // About Us Table
    $createAboutUs = "CREATE TABLE IF NOT EXISTS about_us (
        id INT AUTO_INCREMENT PRIMARY KEY,
        about_text TEXT NOT NULL,
        video_url VARCHAR(500),
        enews_url VARCHAR(500),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createAboutUs)) {
        return ["success" => false, "error" => "Failed to create about_us table: " . $conn->error];
    }

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }

        if (isset($_GET['is_active'])) {
            $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
            $whereClauses[] = "is_active = $is_active";
        }

        $query = "SELECT * FROM about_us";

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $query .= " ORDER BY id DESC LIMIT 1";

        $result = $conn->query($query);
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($data);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'POST':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $requiredFields = ['about_text'];
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

        $columns = [];
        $values = [];

        foreach ($input as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                $columns[] = $key;
                if ($value === null || $value === '') {
                    $values[] = "NULL";
                } else if ($key === 'is_active') {
                    $values[] = (int)$value;
                } else {
                    $escapedValue = $conn->real_escape_string($value);
                    $values[] = "'$escapedValue'";
                }
            }
        }

        if (!in_array('is_active', $columns)) {
            $columns[] = 'is_active';
            $values[] = '1';
        }

        $sql = "INSERT INTO about_us (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";

        if ($conn->query($sql)) {
            $id = $conn->insert_id;
            echo json_encode([
                "success" => true,
                "id" => $id,
                "message" => "About Us added successfully"
            ]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'PATCH':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID required"]);
            break;
        }

        $id = (int)$_GET['id'];
        $updates = [];

        foreach ($input as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                if ($value === null || $value === '') {
                    $updates[] = "$key = NULL";
                } else if ($key === 'is_active') {
                    $updates[] = "$key = " . (int)$value;
                } else {
                    $value = $conn->real_escape_string($value);
                    $updates[] = "$key = '$value'";
                }
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE about_us SET " . implode(", ", $updates) . " WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "updated_rows" => $conn->affected_rows]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID required"]);
            break;
        }

        $id = (int)$_GET['id'];
        $sql = "UPDATE about_us SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "soft_deleted_rows" => $conn->affected_rows]);
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