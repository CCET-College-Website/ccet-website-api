<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createCourses = "CREATE TABLE IF NOT EXISTS `courses` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL UNIQUE,
        description TEXT NOT NULL,
        icon_name VARCHAR(50) DEFAULT 'AcademicCapIcon',
        color_class VARCHAR(100) DEFAULT 'border-blue-800 text-blue-800',
        route_path VARCHAR(100),
        external_link VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createCourses)) {
        return ["success" => false, "error" => "Failed to create courses table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_courses_title ON `courses`(title)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_courses_is_active ON `courses`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_courses_order ON `courses`(display_order)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$tableName = 'courses';

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
                                   ORDER BY display_order ASC, title ASC");
            
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

            if (!empty($_GET['title'])) {
                $title = $conn->real_escape_string($_GET['title']);
                $whereClauses[] = "title LIKE '%$title%'";
            }

            if (!empty($_GET['route_path'])) {
                $route = $conn->real_escape_string($_GET['route_path']);
                $whereClauses[] = "route_path = '$route'";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM `$tableName`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            $query .= " ORDER BY display_order ASC, title ASC";

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

        $requiredFields = ['title', 'description'];
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
        $icon_name = isset($input['icon_name']) ? "'" . $conn->real_escape_string($input['icon_name']) . "'" : "'AcademicCapIcon'";
        $color_class = isset($input['color_class']) ? "'" . $conn->real_escape_string($input['color_class']) . "'" : "'border-blue-800 text-blue-800'";
        $route_path = !empty($input['route_path']) ? "'" . $conn->real_escape_string($input['route_path']) . "'" : "NULL";
        $external_link = !empty($input['external_link']) ? "'" . $conn->real_escape_string($input['external_link']) . "'" : "NULL";
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $checkDuplicate = $conn->query("SELECT id FROM `$tableName` WHERE title = '$title'");
        if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "A course with this title already exists"]);
            break;
        }

        $sql = "INSERT INTO `$tableName` (title, description, icon_name, color_class, route_path, external_link, display_order, is_active) 
                VALUES ('$title', '$description', $icon_name, $color_class, $route_path, $external_link, $display_order, $is_active)";

        if ($conn->query($sql)) {
            $record_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $record_id,
                "title" => $input['title'],
                "message" => "Course added successfully"
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

        $allowedFields = ['title', 'description', 'icon_name', 'color_class', 'route_path', 'external_link', 'display_order', 'is_active'];
        
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