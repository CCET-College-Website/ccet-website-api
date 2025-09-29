<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createGallery = "CREATE TABLE IF NOT EXISTS `campus-tour` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('carousel', 'block') NOT NULL DEFAULT 'carousel',
        block_name VARCHAR(100) NULL,
        image_url VARCHAR(500) NOT NULL,
        title VARCHAR(255),
        description TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_type (type),
        INDEX idx_block_name (block_name),
        INDEX idx_display_order (display_order),
        INDEX idx_is_active (is_active)
    )";
    
    if (!$conn->query($createGallery)) {
        return ["success" => false, "error" => "Failed to create campus-tour table: " . $conn->error];
    }

    return ["success" => true, "message" => "Table created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$resource = isset($_GET['resource']) ? $_GET['resource'] : 'carousel';

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

        if ($resource === 'carousel') {
            $whereClauses[] = "type = 'carousel'";
            
            if (!empty($_GET['keyword'])) {
                $keyword = $conn->real_escape_string($_GET['keyword']);
                $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            }

            $query = "SELECT * FROM `campus-tour` WHERE " . implode(" AND ", $whereClauses);
            $query .= " ORDER BY display_order ASC, id ASC";

        } elseif ($resource === 'blocks') {
            $whereClauses[] = "type = 'block'";
            
            if (!empty($_GET['block_name'])) {
                $block_name = $conn->real_escape_string($_GET['block_name']);
                $whereClauses[] = "block_name = '$block_name'";
            }
            if (!empty($_GET['keyword'])) {
                $keyword = $conn->real_escape_string($_GET['keyword']);
                $whereClauses[] = "(block_name LIKE '%$keyword%' OR title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            }

            $query = "SELECT * FROM `campus-tour` WHERE " . implode(" AND ", $whereClauses);
            $query .= " ORDER BY block_name ASC, display_order ASC, id ASC";

        } else {
            echo json_encode(["success" => false, "error" => "Invalid resource. Use 'carousel' or 'blocks'"]);
            break;
        }

        $result = $conn->query($query);
        if ($result) {
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
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

        if ($resource === 'blocks' && empty($input['block_name'])) {
            echo json_encode(["success" => false, "error" => "Missing required field: block_name for blocks resource"]);
            break;
        }

        $type = $resource === 'carousel' ? 'carousel' : 'block';
        $image_url = $conn->real_escape_string($input['image_url']);
        $block_name = ($resource === 'blocks' && !empty($input['block_name'])) ? 
                      "'" . $conn->real_escape_string($input['block_name']) . "'" : "NULL";
        $title = isset($input['title']) ? "'" . $conn->real_escape_string($input['title']) . "'" : "NULL";
        $description = isset($input['description']) ? "'" . $conn->real_escape_string($input['description']) . "'" : "NULL";
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $sql = "INSERT INTO `campus-tour` (type, block_name, image_url, title, description, display_order, is_active) 
                VALUES ('$type', $block_name, '$image_url', $title, $description, $display_order, $is_active)";

        if ($conn->query($sql)) {
            echo json_encode([
                "success" => true,
                "id" => $conn->insert_id,
                "type" => $type,
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

        $whereClauses = ["type = '" . ($resource === 'carousel' ? 'carousel' : 'block') . "'"];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($resource === 'carousel') {
                $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(block_name LIKE '%$keyword%' OR title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            }
        }
        if ($resource === 'blocks' && !empty($_GET['block_name'])) {
            $block_name = $conn->real_escape_string($_GET['block_name']);
            $whereClauses[] = "block_name = '$block_name'";
        }

        if (count($whereClauses) === 1) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword/block_name required)"]);
            break;
        }

        $updates = [];
        $allowedFields = ['block_name', 'image_url', 'title', 'description', 'display_order', 'is_active'];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($value === null || $value === '') {
                    $updates[] = "$key = NULL";
                } else if (in_array($key, ['display_order', 'is_active'])) {
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
        $sql = "UPDATE `campus-tour` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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

        $whereClauses = ["type = '" . ($resource === 'carousel' ? 'carousel' : 'block') . "'"];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($resource === 'carousel') {
                $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(block_name LIKE '%$keyword%' OR title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            }
        }
        if ($resource === 'blocks' && !empty($_GET['block_name'])) {
            $block_name = $conn->real_escape_string($_GET['block_name']);
            $whereClauses[] = "block_name = '$block_name'";
        }

        if (count($whereClauses) === 1) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword/block_name required)"]);
            break;
        }

        $sql = "UPDATE `campus-tour` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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