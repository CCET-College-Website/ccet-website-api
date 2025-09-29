<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createClassrooms = "CREATE TABLE IF NOT EXISTS classrooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_url VARCHAR(500) NOT NULL,
        title VARCHAR(255),
        description TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createClassrooms)) {
        return ["success" => false, "error" => "Failed to create classrooms table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_classroom_order ON classrooms(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_classroom_active ON classrooms(is_active)");

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

        if (!empty($_GET['title'])) {
            $title = $conn->real_escape_string($_GET['title']);
            $whereClauses[] = "title = '$title'";
        }

        if (isset($_GET['is_active'])) {
            $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
            $whereClauses[] = "is_active = $is_active";
        }

        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
        }

        $query = "SELECT * FROM classrooms";
        
        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $query .= " ORDER BY display_order ASC, id ASC";

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

        if (empty($input['image_url'])) {
            echo json_encode(["success" => false, "error" => "Missing required field: image_url"]);
            break;
        }

        $image_url = $conn->real_escape_string($input['image_url']);
        $title = isset($input['title']) ? $conn->real_escape_string($input['title']) : null;
        $description = isset($input['description']) ? $conn->real_escape_string($input['description']) : null;
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $sql = "INSERT INTO classrooms (image_url, title, description, display_order, is_active) 
                VALUES ('$image_url', " . ($title ? "'$title'" : "NULL") . ", " . 
                ($description ? "'$description'" : "NULL") . ", $display_order, $is_active)";

        if ($conn->query($sql)) {
            $classroom_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "classroom_id" => $classroom_id,
                "image_url" => $input['image_url']
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

        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['title'])) {
            $title = $conn->real_escape_string($_GET['title']);
            $whereClauses[] = "title = '$title'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/title/keyword required)"]);
            break;
        }

        $allowedFields = ['image_url', 'title', 'description', 'display_order', 'is_active'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($value === null || $value === '') {
                    $updates[] = "$key = NULL";
                } else if ($key === 'display_order' || $key === 'is_active') {
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
        $sql = "UPDATE classrooms SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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

        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['title'])) {
            $title = $conn->real_escape_string($_GET['title']);
            $whereClauses[] = "title = '$title'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/title/keyword required)"]);
            break;
        }

        $sql = "UPDATE classrooms SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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