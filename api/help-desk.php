<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createHelpDesk = "CREATE TABLE IF NOT EXISTS helpdesk (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        designation VARCHAR(500) NOT NULL,
        category VARCHAR(100) NOT NULL,
        contact VARCHAR(20) NOT NULL,
        email VARCHAR(255),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createHelpDesk)) {
        return ["success" => false, "error" => "Failed to create helpdesk table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_hd_name ON helpdesk(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hd_category ON helpdesk(category)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hd_contact ON helpdesk(contact)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hd_display_order ON helpdesk(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hd_is_active ON helpdesk(is_active)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidContact($contact) {
    return preg_match('/^[0-9]{10,15}$/', $contact);
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
            $result = $conn->query("SELECT * FROM helpdesk 
                                   WHERE name LIKE '%$keyword%' 
                                   OR designation LIKE '%$keyword%' 
                                   OR category LIKE '%$keyword%'
                                   OR contact LIKE '%$keyword%'
                                   OR email LIKE '%$keyword%'
                                   ORDER BY display_order ASC, id ASC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No helpdesk contacts found with that keyword"]);
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

            if (!empty($_GET['designation'])) {
                $designation = $conn->real_escape_string($_GET['designation']);
                $whereClauses[] = "designation = '$designation'";
            }

            if (!empty($_GET['category'])) {
                $category = $conn->real_escape_string($_GET['category']);
                $whereClauses[] = "category = '$category'";
            }

            if (!empty($_GET['contact'])) {
                $contact = $conn->real_escape_string($_GET['contact']);
                $whereClauses[] = "contact = '$contact'";
            }

            if (!empty($_GET['email'])) {
                $email = $conn->real_escape_string($_GET['email']);
                $whereClauses[] = "email = '$email'";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM helpdesk";
            
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

        $requiredFields = ['name', 'designation', 'category', 'contact'];
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

        if (!isValidContact($input['contact'])) {
            echo json_encode(["success" => false, "error" => "Invalid contact number format (10-15 digits required)"]);
            break;
        }

        $name = $conn->real_escape_string($input['name']);
        $designation = $conn->real_escape_string($input['designation']);
        $category = $conn->real_escape_string($input['category']);
        $contact = $conn->real_escape_string($input['contact']);
        $email = isset($input['email']) ? $conn->real_escape_string($input['email']) : null;
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $sql = "INSERT INTO helpdesk (name, designation, category, contact, email, display_order, is_active) 
                VALUES ('$name', '$designation', '$category', '$contact', " . 
                ($email ? "'$email'" : "NULL") . ", $display_order, $is_active)";

        if ($conn->query($sql)) {
            $helpdesk_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "helpdesk_id" => $helpdesk_id,
                "name" => $input['name'],
                "designation" => $input['designation'],
                "category" => $input['category']
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
        if (!empty($_GET['designation'])) {
            $designation = $conn->real_escape_string($_GET['designation']);
            $whereClauses[] = "designation = '$designation'";
        }
        if (!empty($_GET['category'])) {
            $category = $conn->real_escape_string($_GET['category']);
            $whereClauses[] = "category = '$category'";
        }
        if (!empty($_GET['contact'])) {
            $contact = $conn->real_escape_string($_GET['contact']);
            $whereClauses[] = "contact = '$contact'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR category LIKE '%$keyword%' OR contact LIKE '%$keyword%' OR email LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/name/designation/category/contact/email/keyword required)"]);
            break;
        }

        $allowedFields = ['name', 'designation', 'category', 'contact', 'email', 'display_order', 'is_active'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
                }
                
                if ($key === 'contact' && !empty($value) && !isValidContact($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid contact number format"]);
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
        $sql = "UPDATE helpdesk SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['designation'])) {
            $designation = $conn->real_escape_string($_GET['designation']);
            $whereClauses[] = "designation = '$designation'";
        }
        if (!empty($_GET['category'])) {
            $category = $conn->real_escape_string($_GET['category']);
            $whereClauses[] = "category = '$category'";
        }
        if (!empty($_GET['contact'])) {
            $contact = $conn->real_escape_string($_GET['contact']);
            $whereClauses[] = "contact = '$contact'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR category LIKE '%$keyword%' OR contact LIKE '%$keyword%' OR email LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/name/designation/category/contact/email/keyword required)"]);
            break;
        }

        $sql = "UPDATE helpdesk SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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