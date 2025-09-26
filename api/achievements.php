<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createAchievements = "CREATE TABLE IF NOT EXISTS achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department VARCHAR(100) NOT NULL,
        achievement_type VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createAchievements)) {
        return ["success" => false, "error" => "Failed to create achievements table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_achievements_department ON achievements(department)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_achievements_type ON achievements(achievement_type)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_achievements_created ON achievements(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function achievementsTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'achievements'");
    return $result && $result->num_rows > 0;
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

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $result = $conn->query("SELECT * FROM achievements 
                                   WHERE achievement_type LIKE '%$keyword%' 
                                   OR description LIKE '%$keyword%' 
                                   OR department LIKE '%$keyword%'
                                   ORDER BY id DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No achievements found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['department'])) {
                $department = $conn->real_escape_string($_GET['department']);
                $whereClauses[] = "department = '$department'";
            }

            if (!empty($_GET['achievement_type'])) {
                $achievement_type = $conn->real_escape_string($_GET['achievement_type']);
                $whereClauses[] = "achievement_type = '$achievement_type'";
            }

            $query = "SELECT * FROM achievements";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY id DESC";

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

        $requiredFields = ['department', 'achievement_type', 'description'];
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

        $department = $conn->real_escape_string($input['department']);
        $achievement_type = $conn->real_escape_string($input['achievement_type']);
        $description = $conn->real_escape_string($input['description']);

        $sql = "INSERT INTO achievements (department, achievement_type, description) 
                VALUES ('$department', '$achievement_type', '$description')";

        if ($conn->query($sql)) {
            $achievement_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "achievement_id" => $achievement_id,
                "department" => $department,
                "achievement_type" => $achievement_type
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
        if (!empty($_GET['department'])) {
            $department = $conn->real_escape_string($_GET['department']);
            $whereClauses[] = "department = '$department'";
        }
        if (!empty($_GET['achievement_type'])) {
            $achievement_type = $conn->real_escape_string($_GET['achievement_type']);
            $whereClauses[] = "achievement_type = '$achievement_type'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(achievement_type LIKE '%$keyword%' OR description LIKE '%$keyword%' OR department LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/achievement_type/keyword required)"]);
            break;
        }

        $allowedFields = ['department', 'achievement_type', 'description'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $value = $conn->real_escape_string($value);
                $updates[] = "$key = '$value'";
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE achievements SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['department'])) {
            $department = $conn->real_escape_string($_GET['department']);
            $whereClauses[] = "department = '$department'";
        }
        if (!empty($_GET['achievement_type'])) {
            $achievement_type = $conn->real_escape_string($_GET['achievement_type']);
            $whereClauses[] = "achievement_type = '$achievement_type'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(achievement_type LIKE '%$keyword%' OR description LIKE '%$keyword%' OR department LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/achievement_type/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM achievements WHERE " . implode(" AND ", $whereClauses);

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