<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createOfficials = "CREATE TABLE IF NOT EXISTS `officials-at-ccet` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        image VARCHAR(500),
        post VARCHAR(255) NOT NULL,
        roles TEXT,
        email VARCHAR(255),
        phone_no VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createOfficials)) {
        return ["success" => false, "error" => "Failed to create officials table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_officials_name ON `officials-at-ccet`(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_officials_post ON `officials-at-ccet`(post)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_officials_email ON `officials-at-ccet`(email)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_officials_created ON `officials-at-ccet`(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function officialsTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'officials-at-ccet'");
    return $result && $result->num_rows > 0;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidPhone($phone) {
    return preg_match('/^[\d\s\-\(\)\+]+$/', $phone) && strlen(preg_replace('/[^\d]/', '', $phone)) >= 10;
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
            $result = $conn->query("SELECT * FROM `officials-at-ccet` 
                                   WHERE name LIKE '%$keyword%' 
                                   OR post LIKE '%$keyword%' 
                                   OR roles LIKE '%$keyword%'
                                   OR email LIKE '%$keyword%'
                                   ORDER BY id DESC");
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

            if (!empty($_GET['post'])) {
                $post = $conn->real_escape_string($_GET['post']);
                $whereClauses[] = "post = '$post'";
            }

            if (!empty($_GET['email'])) {
                $email = $conn->real_escape_string($_GET['email']);
                $whereClauses[] = "email = '$email'";
            }

            if (!empty($_GET['role'])) {
                $role = $conn->real_escape_string($_GET['role']);
                $whereClauses[] = "roles LIKE '%$role%'";
            }

            $query = "SELECT * FROM `officials-at-ccet`";
            
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

        $requiredFields = ['name', 'post'];
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

        if (!empty($input['phone_no']) && !isValidPhone($input['phone_no'])) {
            echo json_encode(["success" => false, "error" => "Invalid phone number format"]);
            break;
        }

        $name = $conn->real_escape_string($input['name']);
        $post = $conn->real_escape_string($input['post']);
        $image = isset($input['image']) ? $conn->real_escape_string($input['image']) : null;
        $roles = isset($input['roles']) ? $conn->real_escape_string($input['roles']) : null;
        $email = isset($input['email']) ? $conn->real_escape_string($input['email']) : null;
        $phone_no = isset($input['phone_no']) ? $conn->real_escape_string($input['phone_no']) : null;

        $sql = "INSERT INTO `officials-at-ccet` (name, image, post, roles, email, phone_no) 
                VALUES ('$name', " . 
                ($image ? "'$image'" : "NULL") . ", '$post', " .
                ($roles ? "'$roles'" : "NULL") . ", " .
                ($email ? "'$email'" : "NULL") . ", " .
                ($phone_no ? "'$phone_no'" : "NULL") . ")";

        if ($conn->query($sql)) {
            $official_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "official_id" => $official_id,
                "name" => $input['name'],
                "post" => $input['post']
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
        if (!empty($_GET['post'])) {
            $post = $conn->real_escape_string($_GET['post']);
            $whereClauses[] = "post = '$post'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR post LIKE '%$keyword%' OR roles LIKE '%$keyword%' OR email LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/name/post/email/keyword required)"]);
            break;
        }

        $allowedFields = ['name', 'image', 'post', 'roles', 'email', 'phone_no'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
                }
                
                if ($key === 'phone_no' && !empty($value) && !isValidPhone($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid phone number format"]);
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
        $sql = "UPDATE `officials-at-ccet` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['post'])) {
            $post = $conn->real_escape_string($_GET['post']);
            $whereClauses[] = "post = '$post'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR post LIKE '%$keyword%' OR roles LIKE '%$keyword%' OR email LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/name/post/email/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM `officials-at-ccet` WHERE " . implode(" AND ", $whereClauses);

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