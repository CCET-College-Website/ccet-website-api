<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // NSS Events Table
    $createEvents = "CREATE TABLE IF NOT EXISTS nss_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        image_url VARCHAR(500),
        event_date VARCHAR(50),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createEvents)) {
        return ["success" => false, "error" => "Failed to create nss_events table: " . $conn->error];
    }

    // NSS Sections Table (About, Motto, Objectives, Activities)
    $createSections = "CREATE TABLE IF NOT EXISTS nss_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(100) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createSections)) {
        return ["success" => false, "error" => "Failed to create nss_sections table: " . $conn->error];
    }

    // NSS Forms Table
    $createForms = "CREATE TABLE IF NOT EXISTS nss_forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_name VARCHAR(255) NOT NULL,
        form_url VARCHAR(500) NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createForms)) {
        return ["success" => false, "error" => "Failed to create nss_forms table: " . $conn->error];
    }

    // NSS Hero Section Table
    $createHero = "CREATE TABLE IF NOT EXISTS nss_hero (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hero_title VARCHAR(255) NOT NULL,
        hero_subtitle VARCHAR(255),
        background_image VARCHAR(500),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createHero)) {
        return ["success" => false, "error" => "Failed to create nss_hero table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_events_order ON nss_events(display_order, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_events_date ON nss_events(event_date)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_sections_key ON nss_sections(section_key, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_sections_order ON nss_sections(display_order, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_forms_order ON nss_forms(display_order, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hero_active ON nss_hero(is_active)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (events/sections/forms/hero)"]);
            break;
        }

        $tableMap = [
            'events' => 'nss_events',
            'sections' => 'nss_sections',
            'forms' => 'nss_forms',
            'hero' => 'nss_hero'
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
                case 'events':
                    $searchFields = ['title', 'description', 'event_date'];
                    break;
                case 'sections':
                    $searchFields = ['section_key', 'title', 'content'];
                    break;
                case 'forms':
                    $searchFields = ['form_name'];
                    break;
                case 'hero':
                    $searchFields = ['hero_title', 'hero_subtitle'];
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

            if ($entity === 'sections' && !empty($_GET['section_key'])) {
                $sectionKey = $conn->real_escape_string($_GET['section_key']);
                $whereClauses[] = "section_key = '$sectionKey'";
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (events/sections/forms/hero)"]);
            break;
        }

        $tableMap = [
            'events' => 'nss_events',
            'sections' => 'nss_sections',
            'forms' => 'nss_forms',
            'hero' => 'nss_hero'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];

        $requiredFields = [];
        switch($entity) {
            case 'events':
                $requiredFields = ['title', 'description'];
                break;
            case 'sections':
                $requiredFields = ['section_key', 'title', 'content'];
                break;
            case 'forms':
                $requiredFields = ['form_name', 'form_url'];
                break;
            case 'hero':
                $requiredFields = ['hero_title'];
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

        if (!in_array('display_order', $columns) && $entity !== 'hero') {
            $columns[] = 'display_order';
            $values[] = '0';
        }
        if (!in_array('is_active', $columns)) {
            $columns[] = 'is_active';
            $values[] = '1';
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (events/sections/forms/hero)"]);
            break;
        }

        $tableMap = [
            'events' => 'nss_events',
            'sections' => 'nss_sections',
            'forms' => 'nss_forms',
            'hero' => 'nss_hero'
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
        if (!empty($_GET['section_key']) && $entity === 'sections') {
            $sectionKey = $conn->real_escape_string($_GET['section_key']);
            $whereClauses[] = "section_key = '$sectionKey'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%' OR form_name LIKE '%$keyword%' OR content LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/section_key/keyword required)"]);
            break;
        }

        $updates = [];

        foreach ($input as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (events/sections/forms/hero)"]);
            break;
        }

        $tableMap = [
            'events' => 'nss_events',
            'sections' => 'nss_sections',
            'forms' => 'nss_forms',
            'hero' => 'nss_hero'
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
        if (!empty($_GET['section_key']) && $entity === 'sections') {
            $sectionKey = $conn->real_escape_string($_GET['section_key']);
            $whereClauses[] = "section_key = '$sectionKey'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%' OR form_name LIKE '%$keyword%' OR content LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/section_key/keyword required)"]);
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