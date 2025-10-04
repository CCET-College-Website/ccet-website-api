<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createIncharge = "CREATE TABLE IF NOT EXISTS `scholarship_incharge` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        designation VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        image VARCHAR(500),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createInfo = "CREATE TABLE IF NOT EXISTS `scholarship_info` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        file_url VARCHAR(500) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createIncharge)) {
        return ["success" => false, "error" => "Failed to create scholarship_incharge table: " . $conn->error];
    }

    if (!$conn->query($createInfo)) {
        return ["success" => false, "error" => "Failed to create scholarship_info table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_incharge_name ON `scholarship_incharge`(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_incharge_email ON `scholarship_incharge`(email)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_incharge_is_active ON `scholarship_incharge`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_info_title ON `scholarship_info`(title)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_info_is_active ON `scholarship_info`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_info_display_order ON `scholarship_info`(display_order)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Determine which table to operate on
$table = isset($_GET['table']) ? $_GET['table'] : 'incharge';

if (!in_array($table, ['incharge', 'info'])) {
    echo json_encode(["success" => false, "error" => "Invalid table. Use 'incharge' or 'info'"]);
    exit;
}

$tableName = $table === 'incharge' ? 'scholarship_incharge' : 'scholarship_info';

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            
            if ($table === 'incharge') {
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE name LIKE '%$keyword%' 
                                       OR designation LIKE '%$keyword%'
                                       OR email LIKE '%$keyword%'
                                       ORDER BY name ASC");
            } else {
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE title LIKE '%$keyword%'
                                       ORDER BY display_order ASC, title ASC");
            }
            
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

            if (!empty($_GET['name']) && $table === 'incharge') {
                $name = $conn->real_escape_string($_GET['name']);
                $whereClauses[] = "name LIKE '%$name%'";
            }

            if (!empty($_GET['email']) && $table === 'incharge') {
                $email = $conn->real_escape_string($_GET['email']);
                $whereClauses[] = "email = '$email'";
            }

            if (!empty($_GET['title']) && $table === 'info') {
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
            
            if ($table === 'incharge') {
                $query .= " ORDER BY name ASC";
            } else {
                $query .= " ORDER BY display_order ASC, title ASC";
            }

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

        if ($table === 'incharge') {
            $requiredFields = ['name', 'designation', 'email'];
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

            if (!isValidEmail($input['email'])) {
                echo json_encode(["success" => false, "error" => "Invalid email format"]);
                break;
            }

            $name = $conn->real_escape_string($input['name']);
            $designation = $conn->real_escape_string($input['designation']);
            $email = $conn->real_escape_string($input['email']);
            $image = isset($input['image']) ? $conn->real_escape_string($input['image']) : NULL;
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            $checkDuplicate = $conn->query("SELECT id FROM `$tableName` WHERE email = '$email'");
            if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
                echo json_encode(["success" => false, "error" => "An incharge with this email already exists"]);
                break;
            }

            $sql = "INSERT INTO `$tableName` (name, designation, email, image, is_active) 
                    VALUES ('$name', '$designation', '$email', " . ($image ? "'$image'" : "NULL") . ", $is_active)";

            if ($conn->query($sql)) {
                $record_id = $conn->insert_id;
                echo json_encode([
                    "success" => true, 
                    "id" => $record_id,
                    "name" => $input['name'],
                    "message" => "Scholarship incharge added successfully"
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } else { // scholarship_info
            $requiredFields = ['title', 'file_url'];
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
            $file_url = $conn->real_escape_string($input['file_url']);
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            $sql = "INSERT INTO `$tableName` (title, file_url, is_active, display_order) 
                    VALUES ('$title', '$file_url', $is_active, $display_order)";

            if ($conn->query($sql)) {
                $record_id = $conn->insert_id;
                echo json_encode([
                    "success" => true, 
                    "id" => $record_id,
                    "title" => $input['title'],
                    "message" => "Scholarship info added successfully"
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }
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
        if (!empty($_GET['email']) && $table === 'incharge') {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($table === 'incharge') {
                $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR email LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(title LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/email/keyword required)"]);
            break;
        }

        if ($table === 'incharge') {
            $allowedFields = ['name', 'designation', 'email', 'image', 'is_active'];
        } else {
            $allowedFields = ['title', 'file_url', 'is_active', 'display_order'];
        }
        
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_active' || $key === 'display_order') {
                    $updates[] = "$key = " . (int)$value;
                    continue;
                }

                if ($key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
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
        if (!empty($_GET['email']) && $table === 'incharge') {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($table === 'incharge') {
                $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR email LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(title LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/email/keyword required)"]);
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