<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createStudentWelfare = "CREATE TABLE IF NOT EXISTS student_welfare (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        position VARCHAR(500) NOT NULL,
        email VARCHAR(255) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        image VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createStudentWelfare)) {
        return ["success" => false, "error" => "Failed to create student_welfare table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_sw_name ON student_welfare(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_sw_position ON student_welfare(position)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_sw_email ON student_welfare(email)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_sw_display_order ON student_welfare(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_sw_is_active ON student_welfare(is_active)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidMobile($mobile) {
    return preg_match('/^[0-9]{10,15}$/', $mobile);
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
            $result = $conn->query("SELECT * FROM student_welfare 
                                   WHERE name LIKE '%$keyword%' 
                                   OR position LIKE '%$keyword%' 
                                   OR email LIKE '%$keyword%'
                                   OR mobile LIKE '%$keyword%'
                                   ORDER BY display_order ASC, id ASC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No officials found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['name'])) {
                $name = $conn->real_escape_string($_GET['name']);
                $whereClauses[] = "name = '$name'";
            }

            if (!empty($_GET['position'])) {
                $position = $conn->real_escape_string($_GET['position']);
                $whereClauses[] = "position = '$position'";
            }

            if (!empty($_GET['email'])) {
                $email = $conn->real_escape_string($_GET['email']);
                $whereClauses[] = "email = '$email'";
            }

            if (!empty($_GET['mobile'])) {
                $mobile = $conn->real_escape_string($_GET['mobile']);
                $whereClauses[] = "mobile = '$mobile'";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM student_welfare";
            
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
        }
        break;

    case 'POST':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $requiredFields = ['name', 'position', 'email', 'mobile'];
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

        if (!isValidMobile($input['mobile'])) {
            echo json_encode(["success" => false, "error" => "Invalid mobile number format (10-15 digits required)"]);
            break;
        }

        $name = $conn->real_escape_string($input['name']);
        $position = $conn->real_escape_string($input['position']);
        $email = $conn->real_escape_string($input['email']);
        $mobile = $conn->real_escape_string($input['mobile']);
        $image = isset($input['image']) ? $conn->real_escape_string($input['image']) : null;
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $sql = "INSERT INTO student_welfare (name, position, email, mobile, image, display_order, is_active) 
                VALUES ('$name', '$position', '$email', '$mobile', " . 
                ($image ? "'$image'" : "NULL") . ", $display_order, $is_active)";

        if ($conn->query($sql)) {
            $official_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "official_id" => $official_id,
                "name" => $input['name'],
                "position" => $input['position'],
                "email" => $input['email']
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
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name = '$name'";
        }
        if (!empty($_GET['position'])) {
            $position = $conn->real_escape_string($_GET['position']);
            $whereClauses[] = "position = '$position'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['mobile'])) {
            $mobile = $conn->real_escape_string($_GET['mobile']);
            $whereClauses[] = "mobile = '$mobile'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR position LIKE '%$keyword%' OR email LIKE '%$keyword%' OR mobile LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/name/position/email/mobile/keyword required)"]);
            break;
        }

        $allowedFields = ['name', 'position', 'email', 'mobile', 'image', 'display_order', 'is_active'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
                }
                
                if ($key === 'mobile' && !empty($value) && !isValidMobile($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid mobile number format"]);
                    break 2;
                }
                
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
        $sql = "UPDATE student_welfare SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name = '$name'";
        }
        if (!empty($_GET['position'])) {
            $position = $conn->real_escape_string($_GET['position']);
            $whereClauses[] = "position = '$position'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['mobile'])) {
            $mobile = $conn->real_escape_string($_GET['mobile']);
            $whereClauses[] = "mobile = '$mobile'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR position LIKE '%$keyword%' OR email LIKE '%$keyword%' OR mobile LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/name/position/email/mobile/keyword required)"]);
            break;
        }

        $sql = "UPDATE student_welfare SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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