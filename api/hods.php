<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createHods = "CREATE TABLE IF NOT EXISTS hods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department VARCHAR(255) NOT NULL,
        image VARCHAR(500),
        name VARCHAR(255) NOT NULL,
        designation VARCHAR(255) NOT NULL,
        description TEXT,
        email VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createHods)) {
        return ["success" => false, "error" => "Failed to create hods table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_hods_department ON hods(department)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hods_name ON hods(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hods_designation ON hods(designation)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hods_email ON hods(email)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hods_created ON hods(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function hodsTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'hods'");
    return $result && $result->num_rows > 0;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
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
            $result = $conn->query("SELECT * FROM hods 
                                   WHERE name LIKE '%$keyword%' 
                                   OR department LIKE '%$keyword%' 
                                   OR designation LIKE '%$keyword%'
                                   OR description LIKE '%$keyword%'
                                   OR email LIKE '%$keyword%'
                                   ORDER BY id DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No HoDs found with that keyword"]);
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

            if (!empty($_GET['name'])) {
                $name = $conn->real_escape_string($_GET['name']);
                $whereClauses[] = "name = '$name'";
            }

            if (!empty($_GET['designation'])) {
                $designation = $conn->real_escape_string($_GET['designation']);
                $whereClauses[] = "designation = '$designation'";
            }

            if (!empty($_GET['email'])) {
                $email = $conn->real_escape_string($_GET['email']);
                $whereClauses[] = "email = '$email'";
            }

            $query = "SELECT * FROM hods";
            
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

        $requiredFields = ['department', 'name', 'designation'];
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

        if (!empty($input['email']) && !isValidEmail($input['email'])) {
            echo json_encode(["success" => false, "error" => "Invalid email format"]);
            break;
        }

        $department = $conn->real_escape_string($input['department']);
        $name = $conn->real_escape_string($input['name']);
        $designation = $conn->real_escape_string($input['designation']);
        $image = isset($input['image']) ? $conn->real_escape_string($input['image']) : null;
        $description = isset($input['description']) ? $conn->real_escape_string($input['description']) : null;
        $email = isset($input['email']) ? $conn->real_escape_string($input['email']) : null;

        $sql = "INSERT INTO hods (department, image, name, designation, description, email) 
                VALUES ('$department', " . 
                ($image ? "'$image'" : "NULL") . ", '$name', '$designation', " .
                ($description ? "'$description'" : "NULL") . ", " .
                ($email ? "'$email'" : "NULL") . ")";

        if ($conn->query($sql)) {
            $hod_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "hod_id" => $hod_id,
                "department" => $input['department'],
                "name" => $input['name'],
                "designation" => $input['designation']
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
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name = '$name'";
        }
        if (!empty($_GET['designation'])) {
            $designation = $conn->real_escape_string($_GET['designation']);
            $whereClauses[] = "designation = '$designation'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR department LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR description LIKE '%$keyword%' OR email LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/name/designation/email/keyword required)"]);
            break;
        }

        $allowedFields = ['department', 'image', 'name', 'designation', 'description', 'email'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
                }
                
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
        $sql = "UPDATE hods SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name = '$name'";
        }
        if (!empty($_GET['designation'])) {
            $designation = $conn->real_escape_string($_GET['designation']);
            $whereClauses[] = "designation = '$designation'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR department LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR description LIKE '%$keyword%' OR email LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/name/designation/email/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM hods WHERE " . implode(" AND ", $whereClauses);

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