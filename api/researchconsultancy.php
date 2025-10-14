<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createResearch = "CREATE TABLE IF NOT EXISTS `research_consultancy` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        image_url VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createResearch)) {
        return ["success" => false, "error" => "Failed to create research_consultancy table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_research_section ON `research_consultancy`(section)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_research_is_active ON `research_consultancy`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_research_order ON `research_consultancy`(display_order)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$tableName = 'research_consultancy';

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            
            $result = $conn->query("SELECT * FROM `$tableName` 
                                   WHERE title LIKE '%$keyword%' 
                                   OR description LIKE '%$keyword%'
                                   OR section LIKE '%$keyword%'
                                   ORDER BY display_order ASC, section ASC");
            
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No records found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['section'])) {
                $section = $conn->real_escape_string($_GET['section']);
                $whereClauses[] = "section = '$section'";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM `$tableName`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            $query .= " ORDER BY display_order ASC, section ASC";

            $result = $conn->query($query);
            if ($result) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }
        }
        break;

    case 'POST':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $requiredFields = ['section', 'title'];
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

        $section = $conn->real_escape_string($input['section']);
        $title = $conn->real_escape_string($input['title']);
        $description = !empty($input['description']) ? "'" . $conn->real_escape_string($input['description']) . "'" : "NULL";
        $image_url = !empty($input['image_url']) ? "'" . $conn->real_escape_string($input['image_url']) . "'" : "NULL";
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $sql = "INSERT INTO `$tableName` (section, title, description, image_url, display_order, is_active) 
                VALUES ('$section', '$title', $description, $image_url, $display_order, $is_active)";

        if ($conn->query($sql)) {
            $record_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $record_id,
                "section" => $input['section'],
                "title" => $input['title'],
                "message" => "Research consultancy item added successfully"
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
        if (!empty($_GET['section'])) {
            $section = $conn->real_escape_string($_GET['section']);
            $whereClauses[] = "section = '$section'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%' OR section LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/section/keyword required)"]);
            break;
        }

        $allowedFields = ['section', 'title', 'description', 'image_url', 'display_order', 'is_active'];
        
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_active' || $key === 'display_order') {
                    $updates[] = "$key = " . (int)$value;
                    continue;
                }

                if (is_null($value) || $value === '') {
                    $updates[] = "$key = NULL";
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
        $sql = "UPDATE `$tableName` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['section'])) {
            $section = $conn->real_escape_string($_GET['section']);
            $whereClauses[] = "section = '$section'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%' OR section LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/section/keyword required)"]);
            break;
        }

        $sql = "UPDATE `$tableName` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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