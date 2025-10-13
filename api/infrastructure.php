<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createInfrastructure = "CREATE TABLE IF NOT EXISTS `infrastructure` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createInfrastructure)) {
        return ["success" => false, "error" => "Failed to create infrastructure table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_infrastructure_category ON `infrastructure`(category)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_infrastructure_is_active ON `infrastructure`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_infrastructure_order ON `infrastructure`(display_order)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$tableName = 'infrastructure';

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
                                   OR category LIKE '%$keyword%'
                                   ORDER BY display_order ASC, category ASC");
            
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

            if (!empty($_GET['category'])) {
                $category = $conn->real_escape_string($_GET['category']);
                $whereClauses[] = "category LIKE '%$category%'";
            }

            if (!empty($_GET['title'])) {
                $title = $conn->real_escape_string($_GET['title']);
                $whereClauses[] = "title LIKE '%$title%'";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM `$tableName`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            $query .= " ORDER BY display_order ASC, category ASC";

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

        $requiredFields = ['category', 'title'];
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

        $category = $conn->real_escape_string($input['category']);
        $title = $conn->real_escape_string($input['title']);
        $description = !empty($input['description']) ? "'" . $conn->real_escape_string($input['description']) . "'" : "NULL";
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $sql = "INSERT INTO `$tableName` (category, title, description, display_order, is_active) 
                VALUES ('$category', '$title', $description, $display_order, $is_active)";

        if ($conn->query($sql)) {
            $record_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $record_id,
                "category" => $input['category'],
                "title" => $input['title'],
                "message" => "Infrastructure item added successfully"
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
        if (!empty($_GET['category'])) {
            $category = $conn->real_escape_string($_GET['category']);
            $whereClauses[] = "category = '$category'";
        }
        if (!empty($_GET['title'])) {
            $title = $conn->real_escape_string($_GET['title']);
            $whereClauses[] = "title = '$title'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%' OR category LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/category/title/keyword required)"]);
            break;
        }

        $allowedFields = ['category', 'title', 'description', 'display_order', 'is_active'];
        
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
        if (!empty($_GET['category'])) {
            $category = $conn->real_escape_string($_GET['category']);
            $whereClauses[] = "category = '$category'";
        }
        if (!empty($_GET['title'])) {
            $title = $conn->real_escape_string($_GET['title']);
            $whereClauses[] = "title = '$title'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%' OR category LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/category/title/keyword required)"]);
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