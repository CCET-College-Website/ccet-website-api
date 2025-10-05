<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Hostel Staff Table
    $createStaff = "CREATE TABLE IF NOT EXISTS hostel_staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hostel_type ENUM('boys', 'girls') NOT NULL,
        name VARCHAR(255) NOT NULL,
        designation VARCHAR(500) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        email VARCHAR(255),
        profile_image VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createStaff)) {
        return ["success" => false, "error" => "Failed to create hostel_staff table: " . $conn->error];
    }

    // Hostel Notices Table
    $createNotices = "CREATE TABLE IF NOT EXISTS hostel_notices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hostel_type ENUM('boys', 'girls', 'both') NOT NULL,
        title VARCHAR(500) NOT NULL,
        description TEXT,
        file_url VARCHAR(500),
        notice_date DATE,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createNotices)) {
        return ["success" => false, "error" => "Failed to create hostel_notices table: " . $conn->error];
    }

    // Hostel Forms Table
    $createForms = "CREATE TABLE IF NOT EXISTS hostel_forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hostel_type ENUM('boys', 'girls', 'both') NOT NULL,
        form_name VARCHAR(255) NOT NULL,
        description TEXT,
        file_url VARCHAR(500) NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createForms)) {
        return ["success" => false, "error" => "Failed to create hostel_forms table: " . $conn->error];
    }

    // Hostel Gallery Table
    $createGallery = "CREATE TABLE IF NOT EXISTS hostel_gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hostel_type ENUM('boys', 'girls', 'both') NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        alt_text VARCHAR(255),
        caption TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createGallery)) {
        return ["success" => false, "error" => "Failed to create hostel_gallery table: " . $conn->error];
    }

    // Hostel Rules Table
    $createRules = "CREATE TABLE IF NOT EXISTS hostel_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hostel_type ENUM('boys', 'girls', 'both') NOT NULL,
        rule_text TEXT NOT NULL,
        rule_type ENUM('general', 'disciplinary') DEFAULT 'general',
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createRules)) {
        return ["success" => false, "error" => "Failed to create hostel_rules table: " . $conn->error];
    }

    // Hostel Documents Table (for PDF rules and other documents)
    $createDocuments = "CREATE TABLE IF NOT EXISTS hostel_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hostel_type ENUM('boys', 'girls', 'both') NOT NULL,
        title VARCHAR(500) NOT NULL,
        file_url VARCHAR(500) NOT NULL,
        description TEXT,
        document_type VARCHAR(100),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createDocuments)) {
        return ["success" => false, "error" => "Failed to create hostel_documents table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_staff_hostel_type ON hostel_staff(hostel_type, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_staff_display_order ON hostel_staff(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_notices_hostel_type ON hostel_notices(hostel_type, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_notices_date ON hostel_notices(notice_date)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_forms_hostel_type ON hostel_forms(hostel_type, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_hostel_type ON hostel_gallery(hostel_type, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_rules_hostel_type ON hostel_rules(hostel_type, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_rules_type ON hostel_rules(rule_type)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_documents_hostel_type ON hostel_documents(hostel_type, is_active)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidContact($contact) {
    return preg_match('/^[+]?[0-9]{10,15}$/', $contact);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$entity = isset($_GET['entity']) ? $_GET['entity'] : null;

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (!$entity) {
            echo json_encode(["success" => false, "error" => "Entity parameter required (staff/notices/forms/gallery/rules/documents)"]);
            break;
        }

        $table = "hostel_" . $entity;
        $validTables = ['hostel_staff', 'hostel_notices', 'hostel_forms', 'hostel_gallery', 'hostel_rules', 'hostel_documents'];
        
        if (!in_array($table, $validTables)) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $searchFields = [];
            
            switch($entity) {
                case 'staff':
                    $searchFields = ['name', 'designation', 'mobile', 'email'];
                    break;
                case 'notices':
                    $searchFields = ['title', 'description'];
                    break;
                case 'forms':
                    $searchFields = ['form_name', 'description'];
                    break;
                case 'gallery':
                    $searchFields = ['alt_text', 'caption'];
                    break;
                case 'rules':
                    $searchFields = ['rule_text'];
                    break;
                case 'documents':
                    $searchFields = ['title', 'description'];
                    break;
            }
            
            $searchConditions = array_map(function($field) use ($keyword) {
                return "$field LIKE '%$keyword%'";
            }, $searchFields);
            
            $whereClause = "(" . implode(" OR ", $searchConditions) . ")";
            
            if (!empty($_GET['hostel_type'])) {
                $hostelType = $conn->real_escape_string($_GET['hostel_type']);
                $whereClause .= " AND (hostel_type = '$hostelType' OR hostel_type = 'both')";
            }
            
            $result = $conn->query("SELECT * FROM $table WHERE $whereClause ORDER BY display_order ASC, id ASC");
            
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

            if (!empty($_GET['hostel_type'])) {
                $hostelType = $conn->real_escape_string($_GET['hostel_type']);
                if (in_array($entity, ['notices', 'forms', 'gallery', 'rules', 'documents'])) {
                    $whereClauses[] = "(hostel_type = '$hostelType' OR hostel_type = 'both')";
                } else {
                    $whereClauses[] = "hostel_type = '$hostelType'";
                }
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            if ($entity === 'staff' && !empty($_GET['name'])) {
                $name = $conn->real_escape_string($_GET['name']);
                $whereClauses[] = "name = '$name'";
            }

            if ($entity === 'notices' && !empty($_GET['notice_date'])) {
                $noticeDate = $conn->real_escape_string($_GET['notice_date']);
                $whereClauses[] = "notice_date = '$noticeDate'";
            }

            if ($entity === 'rules' && !empty($_GET['rule_type'])) {
                $ruleType = $conn->real_escape_string($_GET['rule_type']);
                $whereClauses[] = "rule_type = '$ruleType'";
            }

            if ($entity === 'documents' && !empty($_GET['document_type'])) {
                $documentType = $conn->real_escape_string($_GET['document_type']);
                $whereClauses[] = "document_type = '$documentType'";
            }

            $query = "SELECT * FROM $table";
            
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

        if (!$entity) {
            echo json_encode(["success" => false, "error" => "Entity parameter required (staff/notices/forms/gallery/rules/documents)"]);
            break;
        }

        $table = "hostel_" . $entity;
        $validTables = ['hostel_staff', 'hostel_notices', 'hostel_forms', 'hostel_gallery', 'hostel_rules', 'hostel_documents'];
        
        if (!in_array($table, $validTables)) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $requiredFields = ['hostel_type'];
        switch($entity) {
            case 'staff':
                $requiredFields = array_merge($requiredFields, ['name', 'designation', 'mobile']);
                break;
            case 'notices':
                $requiredFields = array_merge($requiredFields, ['title']);
                break;
            case 'forms':
                $requiredFields = array_merge($requiredFields, ['form_name', 'file_url']);
                break;
            case 'gallery':
                $requiredFields = array_merge($requiredFields, ['image_url']);
                break;
            case 'rules':
                $requiredFields = array_merge($requiredFields, ['rule_text']);
                break;
            case 'documents':
                $requiredFields = array_merge($requiredFields, ['title', 'file_url']);
                break;
        }

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

        if ($entity === 'staff') {
            if (!empty($input['email']) && !isValidEmail($input['email'])) {
                echo json_encode(["success" => false, "error" => "Invalid email format"]);
                break;
            }

            if (!isValidContact($input['mobile'])) {
                echo json_encode(["success" => false, "error" => "Invalid mobile number format"]);
                break;
            }
        }

        $columns = [];
        $values = [];
        
        foreach ($input as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                $columns[] = $key;
                if ($value === null || $value === '') {
                    $values[] = "NULL";
                } else if (in_array($key, ['display_order', 'is_active'])) {
                    $values[] = (int)$value;
                } else {
                    $escapedValue = $conn->real_escape_string($value);
                    $values[] = "'$escapedValue'";
                }
            }
        }

        if (!in_array('display_order', $columns)) {
            $columns[] = 'display_order';
            $values[] = '0';
        }
        if (!in_array('is_active', $columns)) {
            $columns[] = 'is_active';
            $values[] = '1';
        }

        if ($entity === 'rules' && !in_array('rule_type', $columns)) {
            $columns[] = 'rule_type';
            $values[] = "'general'";
        }

        $sql = "INSERT INTO $table (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";

        if ($conn->query($sql)) {
            $id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $id,
                "entity" => $entity,
                "hostel_type" => $input['hostel_type']
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

        if (!$entity) {
            echo json_encode(["success" => false, "error" => "Entity parameter required (staff/notices/forms/gallery/rules/documents)"]);
            break;
        }

        $table = "hostel_" . $entity;
        $validTables = ['hostel_staff', 'hostel_notices', 'hostel_forms', 'hostel_gallery', 'hostel_rules', 'hostel_documents'];
        
        if (!in_array($table, $validTables)) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['hostel_type'])) {
            $hostelType = $conn->real_escape_string($_GET['hostel_type']);
            $whereClauses[] = "hostel_type = '$hostelType'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR title LIKE '%$keyword%' OR rule_text LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/hostel_type/keyword required)"]);
            break;
        }

        $updates = [];

        foreach ($input as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                if ($entity === 'staff' && $key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
                }
                
                if ($entity === 'staff' && $key === 'mobile' && !empty($value) && !isValidContact($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid mobile number format"]);
                    break 2;
                }
                
                if ($value === null || $value === '') {
                    $updates[] = "$key = NULL";
                } else if (in_array($key, ['display_order', 'is_active'])) {
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
        $sql = "UPDATE $table SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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

        if (!$entity) {
            echo json_encode(["success" => false, "error" => "Entity parameter required (staff/notices/forms/gallery/rules/documents)"]);
            break;
        }

        $table = "hostel_" . $entity;
        $validTables = ['hostel_staff', 'hostel_notices', 'hostel_forms', 'hostel_gallery', 'hostel_rules', 'hostel_documents'];
        
        if (!in_array($table, $validTables)) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['hostel_type'])) {
            $hostelType = $conn->real_escape_string($_GET['hostel_type']);
            $whereClauses[] = "hostel_type = '$hostelType'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR title LIKE '%$keyword%' OR rule_text LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/hostel_type/keyword required)"]);
            break;
        }

        $sql = "UPDATE $table SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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