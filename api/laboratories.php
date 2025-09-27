<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createLaboratories = "CREATE TABLE IF NOT EXISTS `laboratories` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department VARCHAR(255) NOT NULL,
        lab_name VARCHAR(255) NOT NULL,
        lab_image VARCHAR(255),
        lab_description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createLaboratories)) {
        return ["success" => false, "error" => "Failed to create laboratories table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_laboratories_department ON `laboratories`(department)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_laboratories_name ON `laboratories`(lab_name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_laboratories_created ON `laboratories`(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function laboratoriesTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'laboratories'");
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
            $result = $conn->query("SELECT * FROM `laboratories` 
                                   WHERE department LIKE '%$keyword%' 
                                   OR lab_name LIKE '%$keyword%' 
                                   OR lab_description LIKE '%$keyword%'
                                   ORDER BY id DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No laboratories found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['department'])) {
                $department = $conn->real_escape_string($_GET['department']);
                $whereClauses[] = "department = '$department'";
            }

            if (!empty($_GET['lab_name'])) {
                $lab_name = $conn->real_escape_string($_GET['lab_name']);
                $whereClauses[] = "lab_name = '$lab_name'";
            }

            $query = "SELECT * FROM `laboratories`";
            
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

        $requiredFields = ['department', 'lab_name'];
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
        $lab_name = $conn->real_escape_string($input['lab_name']);
        $lab_image = isset($input['lab_image']) ? $conn->real_escape_string($input['lab_image']) : null;
        $lab_description = isset($input['lab_description']) ? $conn->real_escape_string($input['lab_description']) : null;

        $sql = "INSERT INTO `laboratories` (department, lab_name, lab_image, lab_description) 
                VALUES ('$department', '$lab_name', " . 
                ($lab_image ? "'$lab_image'" : "NULL") . ", " .
                ($lab_description ? "'$lab_description'" : "NULL") . ")";

        if ($conn->query($sql)) {
            $laboratory_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "laboratory_id" => $laboratory_id,
                "department" => $input['department'],
                "lab_name" => $input['lab_name']
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
        if (!empty($_GET['lab_name'])) {
            $lab_name = $conn->real_escape_string($_GET['lab_name']);
            $whereClauses[] = "lab_name = '$lab_name'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department LIKE '%$keyword%' OR lab_name LIKE '%$keyword%' OR lab_description LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/lab_name/keyword required)"]);
            break;
        }

        $allowedFields = ['department', 'lab_name', 'lab_image', 'lab_description'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($value === null || $value === '') {
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
        $sql = "UPDATE `laboratories` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['lab_name'])) {
            $lab_name = $conn->real_escape_string($_GET['lab_name']);
            $whereClauses[] = "lab_name = '$lab_name'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department LIKE '%$keyword%' OR lab_name LIKE '%$keyword%' OR lab_description LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/lab_name/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM `laboratories` WHERE " . implode(" AND ", $whereClauses);

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