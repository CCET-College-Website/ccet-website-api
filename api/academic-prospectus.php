<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createProspectus = "CREATE TABLE IF NOT EXISTS `academic_prospectus` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year VARCHAR(20) NOT NULL UNIQUE,
        url VARCHAR(500) NOT NULL,
        is_current BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createProspectus)) {
        return ["success" => false, "error" => "Failed to create academic_prospectus table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_prospectus_year ON `academic_prospectus`(year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_prospectus_is_current ON `academic_prospectus`(is_current)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_prospectus_is_active ON `academic_prospectus`(is_active)");

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

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $result = $conn->query("SELECT * FROM `academic_prospectus` 
                                   WHERE year LIKE '%$keyword%' 
                                   OR url LIKE '%$keyword%'
                                   ORDER BY year DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No prospectus found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['year'])) {
                $year = $conn->real_escape_string($_GET['year']);
                $whereClauses[] = "year = '$year'";
            }

            if (isset($_GET['is_current'])) {
                $is_current = $_GET['is_current'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_current = $is_current";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM `academic_prospectus`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY year DESC";

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

        $requiredFields = ['year', 'url'];
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

        $year = $conn->real_escape_string($input['year']);
        $url = $conn->real_escape_string($input['url']);
        $is_current = isset($input['is_current']) ? (int)$input['is_current'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $checkDuplicate = $conn->query("SELECT id FROM `academic_prospectus` WHERE year = '$year'");
        if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "Prospectus for this year already exists"]);
            break;
        }

        // If marking as current, unmark all others
        if ($is_current) {
            $conn->query("UPDATE `academic_prospectus` SET is_current = FALSE");
        }

        $sql = "INSERT INTO `academic_prospectus` (year, url, is_current, is_active) 
                VALUES ('$year', '$url', $is_current, $is_active)";

        if ($conn->query($sql)) {
            $prospectus_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "prospectus_id" => $prospectus_id,
                "year" => $input['year'],
                "message" => "Prospectus added successfully"
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
        if (!empty($_GET['year'])) {
            $year = $conn->real_escape_string($_GET['year']);
            $whereClauses[] = "year = '$year'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(year LIKE '%$keyword%' OR url LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/year/keyword required)"]);
            break;
        }

        $allowedFields = ['year', 'url', 'is_current', 'is_active'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_current' || $key === 'is_active') {
                    // If marking as current, unmark all others first
                    if ($key === 'is_current' && (int)$value === 1) {
                        $conn->query("UPDATE `academic_prospectus` SET is_current = FALSE");
                    }
                    $updates[] = "$key = " . (int)$value;
                    continue;
                }

                if (empty($value)) {
                    echo json_encode(["success" => false, "error" => "$key field cannot be empty"]);
                    break 2;
                }

                $value = $conn->real_escape_string($value);
                $updates[] = "$key = '$value'";
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE `academic_prospectus` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['year'])) {
            $year = $conn->real_escape_string($_GET['year']);
            $whereClauses[] = "year = '$year'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(year LIKE '%$keyword%' OR url LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/year/keyword required)"]);
            break;
        }

        $sql = "UPDATE `academic_prospectus` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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