<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Anti-Ragging Posters Table
    $createPosters = "CREATE TABLE IF NOT EXISTS anti_ragging_posters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_url VARCHAR(500) NOT NULL,
        alt_text VARCHAR(255),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createPosters)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_posters table: " . $conn->error];
    }

    // Anti-Ragging Documents Table
    $createDocuments = "CREATE TABLE IF NOT EXISTS anti_ragging_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_title VARCHAR(255) NOT NULL,
        document_url VARCHAR(500) NOT NULL,
        document_type ENUM('regulation', 'affidavit', 'annexure', 'instruction', 'other') DEFAULT 'other',
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createDocuments)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_documents table: " . $conn->error];
    }

    // Anti-Ragging Contacts Table
    $createContacts = "CREATE TABLE IF NOT EXISTS anti_ragging_contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        helpline_name VARCHAR(255) NOT NULL,
        designation VARCHAR(255),
        phone VARCHAR(20),
        email VARCHAR(255),
        website VARCHAR(255),
        is_national BOOLEAN DEFAULT FALSE,
        is_institute BOOLEAN DEFAULT FALSE,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createContacts)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_contacts table: " . $conn->error];
    }

    // Anti-Ragging Info Table (for main content/instructions)
    $createInfo = "CREATE TABLE IF NOT EXISTS anti_ragging_info (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(100) NOT NULL UNIQUE,
        content_text TEXT NOT NULL,
        content_type ENUM('paragraph', 'list_item', 'heading', 'bold_text') DEFAULT 'paragraph',
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createInfo)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_info table: " . $conn->error];
    }

    // Anti-Ragging Institute Details Table
    $createInstituteDetails = "CREATE TABLE IF NOT EXISTS anti_ragging_institute_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        detail_key VARCHAR(100) NOT NULL UNIQUE,
        detail_value VARCHAR(500) NOT NULL,
        detail_label VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createInstituteDetails)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_institute_details table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_posters_order ON anti_ragging_posters(display_order, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_documents_type ON anti_ragging_documents(document_type, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_contacts_national ON anti_ragging_contacts(is_national, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_contacts_institute ON anti_ragging_contacts(is_institute, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_info_section ON anti_ragging_info(section_key, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_institute_key ON anti_ragging_institute_details(detail_key)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidPhone($phone) {
    return preg_match('/^[+]?[0-9\s\-()]{10,20}$/', $phone);
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (posters/documents/contacts/info/institute_details)"]);
            break;
        }

        $tableMap = [
            'posters' => 'anti_ragging_posters',
            'documents' => 'anti_ragging_documents',
            'contacts' => 'anti_ragging_contacts',
            'info' => 'anti_ragging_info',
            'institute_details' => 'anti_ragging_institute_details'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $searchFields = [];
            
            switch($entity) {
                case 'posters':
                    $searchFields = ['alt_text'];
                    break;
                case 'documents':
                    $searchFields = ['document_title'];
                    break;
                case 'contacts':
                    $searchFields = ['helpline_name', 'designation', 'phone', 'email'];
                    break;
                case 'info':
                    $searchFields = ['section_key', 'content_text'];
                    break;
                case 'institute_details':
                    $searchFields = ['detail_key', 'detail_value', 'detail_label'];
                    break;
            }
            
            $searchConditions = array_map(function($field) use ($keyword) {
                return "$field LIKE '%$keyword%'";
            }, $searchFields);
            
            $result = $conn->query("SELECT * FROM $table WHERE (" . implode(" OR ", $searchConditions) . ") ORDER BY display_order ASC, id ASC");
            
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

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            if ($entity === 'documents' && !empty($_GET['document_type'])) {
                $docType = $conn->real_escape_string($_GET['document_type']);
                $whereClauses[] = "document_type = '$docType'";
            }

            if ($entity === 'contacts') {
                if (isset($_GET['is_national'])) {
                    $isNational = $_GET['is_national'] === 'true' ? 1 : 0;
                    $whereClauses[] = "is_national = $isNational";
                }
                if (isset($_GET['is_institute'])) {
                    $isInstitute = $_GET['is_institute'] === 'true' ? 1 : 0;
                    $whereClauses[] = "is_institute = $isInstitute";
                }
            }

            if ($entity === 'info' && !empty($_GET['section_key'])) {
                $sectionKey = $conn->real_escape_string($_GET['section_key']);
                $whereClauses[] = "section_key = '$sectionKey'";
            }

            if ($entity === 'info' && !empty($_GET['content_type'])) {
                $contentType = $conn->real_escape_string($_GET['content_type']);
                $whereClauses[] = "content_type = '$contentType'";
            }

            if ($entity === 'institute_details' && !empty($_GET['detail_key'])) {
                $detailKey = $conn->real_escape_string($_GET['detail_key']);
                $whereClauses[] = "detail_key = '$detailKey'";
            }

            $query = "SELECT * FROM $table";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            if ($entity === 'institute_details') {
                $query .= " ORDER BY id ASC";
            } else {
                $query .= " ORDER BY display_order ASC, id ASC";
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

        if (!$entity) {
            echo json_encode(["success" => false, "error" => "Entity parameter required (posters/documents/contacts/info/institute_details)"]);
            break;
        }

        $tableMap = [
            'posters' => 'anti_ragging_posters',
            'documents' => 'anti_ragging_documents',
            'contacts' => 'anti_ragging_contacts',
            'info' => 'anti_ragging_info',
            'institute_details' => 'anti_ragging_institute_details'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];

        $requiredFields = [];
        switch($entity) {
            case 'posters':
                $requiredFields = ['image_url'];
                break;
            case 'documents':
                $requiredFields = ['document_title', 'document_url'];
                break;
            case 'contacts':
                $requiredFields = ['helpline_name'];
                break;
            case 'info':
                $requiredFields = ['section_key', 'content_text'];
                break;
            case 'institute_details':
                $requiredFields = ['detail_key', 'detail_value'];
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

        // Additional validations
        if ($entity === 'contacts') {
            if (!empty($input['email']) && !isValidEmail($input['email'])) {
                echo json_encode(["success" => false, "error" => "Invalid email format"]);
                break;
            }
            if (!empty($input['phone']) && !isValidPhone($input['phone'])) {
                echo json_encode(["success" => false, "error" => "Invalid phone format"]);
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
                } else if (in_array($key, ['display_order', 'is_active', 'is_national', 'is_institute'])) {
                    $values[] = (int)$value;
                } else {
                    $escapedValue = $conn->real_escape_string($value);
                    $values[] = "'$escapedValue'";
                }
            }
        }

        // Set defaults
        if (!in_array('display_order', $columns) && $entity !== 'institute_details') {
            $columns[] = 'display_order';
            $values[] = '0';
        }
        if (!in_array('is_active', $columns)) {
            $columns[] = 'is_active';
            $values[] = '1';
        }
        if ($entity === 'documents' && !in_array('document_type', $columns)) {
            $columns[] = 'document_type';
            $values[] = "'other'";
        }
        if ($entity === 'info' && !in_array('content_type', $columns)) {
            $columns[] = 'content_type';
            $values[] = "'paragraph'";
        }
        if ($entity === 'contacts' && !in_array('is_national', $columns)) {
            $columns[] = 'is_national';
            $values[] = '0';
        }
        if ($entity === 'contacts' && !in_array('is_institute', $columns)) {
            $columns[] = 'is_institute';
            $values[] = '0';
        }

        $sql = "INSERT INTO $table (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";

        if ($conn->query($sql)) {
            $id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $id,
                "entity" => $entity
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (posters/documents/contacts/info/institute_details)"]);
            break;
        }

        $tableMap = [
            'posters' => 'anti_ragging_posters',
            'documents' => 'anti_ragging_documents',
            'contacts' => 'anti_ragging_contacts',
            'info' => 'anti_ragging_info',
            'institute_details' => 'anti_ragging_institute_details'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];
        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['section_key']) && $entity === 'info') {
            $sectionKey = $conn->real_escape_string($_GET['section_key']);
            $whereClauses[] = "section_key = '$sectionKey'";
        }
        if (!empty($_GET['detail_key']) && $entity === 'institute_details') {
            $detailKey = $conn->real_escape_string($_GET['detail_key']);
            $whereClauses[] = "detail_key = '$detailKey'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(helpline_name LIKE '%$keyword%' OR document_title LIKE '%$keyword%' OR content_text LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/section_key/detail_key/keyword required)"]);
            break;
        }

        $updates = [];

        foreach ($input as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                if ($entity === 'contacts' && $key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
                }
                if ($entity === 'contacts' && $key === 'phone' && !empty($value) && !isValidPhone($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid phone format"]);
                    break 2;
                }
                
                if ($value === null || $value === '') {
                    $updates[] = "$key = NULL";
                } else if (in_array($key, ['display_order', 'is_active', 'is_national', 'is_institute'])) {
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (posters/documents/contacts/info/institute_details)"]);
            break;
        }

        $tableMap = [
            'posters' => 'anti_ragging_posters',
            'documents' => 'anti_ragging_documents',
            'contacts' => 'anti_ragging_contacts',
            'info' => 'anti_ragging_info',
            'institute_details' => 'anti_ragging_institute_details'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];
        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['section_key']) && $entity === 'info') {
            $sectionKey = $conn->real_escape_string($_GET['section_key']);
            $whereClauses[] = "section_key = '$sectionKey'";
        }
        if (!empty($_GET['detail_key']) && $entity === 'institute_details') {
            $detailKey = $conn->real_escape_string($_GET['detail_key']);
            $whereClauses[] = "detail_key = '$detailKey'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(helpline_name LIKE '%$keyword%' OR document_title LIKE '%$keyword%' OR content_text LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/section_key/detail_key/keyword required)"]);
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