<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesInCcetMaster($conn) {
    // Step 1: Select the ccet_master database (assuming it already exists)
    if (!$conn->select_db('ccet_master')) {
        return ["success" => false, "error" => "Failed to select ccet_master database: " . $conn->error];
    }
    
    // Step 2: Create footer navigation tables in ccet_master database
    
    // Table 1: Footer sections (like "Explore", "Important Links", etc.)
    $createFooterSections = "CREATE TABLE IF NOT EXISTS `footer_sections` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_name VARCHAR(100) NOT NULL UNIQUE,
        section_order INT NOT NULL DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_section_order (section_order, is_active),
        INDEX idx_section_name (section_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Table 2: Footer links under sections
    $createFooterLinks = "CREATE TABLE IF NOT EXISTS `footer_links` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_id INT NOT NULL,
        link_name VARCHAR(150) NOT NULL,
        link_url VARCHAR(500) NOT NULL,
        is_external BOOLEAN DEFAULT FALSE,
        link_order INT NOT NULL DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (section_id) REFERENCES footer_sections(id) ON DELETE CASCADE ON UPDATE CASCADE,
        INDEX idx_section_link (section_id, link_order, is_active),
        INDEX idx_link_name (link_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Table 3: Footer bottom navigation (copyright area links)
    $createFooterBottomLinks = "CREATE TABLE IF NOT EXISTS `footer_bottom_links` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_name VARCHAR(100) NOT NULL,
        link_url VARCHAR(255) NOT NULL,
        is_external BOOLEAN DEFAULT FALSE,
        link_order INT NOT NULL DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_bottom_link_order (link_order, is_active),
        INDEX idx_bottom_link_name (link_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Execute table creation queries
    $tables = [
        'footer_sections' => $createFooterSections,
        'footer_links' => $createFooterLinks,
        'footer_bottom_links' => $createFooterBottomLinks
    ];

    $createdTables = [];
    foreach ($tables as $tableName => $createSql) {
        if ($conn->query($createSql)) {
            $createdTables[] = $tableName;
        } else {
            return ["success" => false, "error" => "Failed to create $tableName table: " . $conn->error];
        }
    }

    return [
        "success" => true, 
        "message" => "Footer navigation tables created successfully in ccet_master database",
        "database" => "ccet_master",
        "tables_created" => $createdTables,
        "table_count" => count($createdTables)
    ];
}

function validateSection($input) {
    if (empty($input['section_name'])) {
        return ["valid" => false, "error" => "Section name is required"];
    }
    
    if (strlen($input['section_name']) > 100) {
        return ["valid" => false, "error" => "Section name must be 100 characters or less"];
    }
    
    return ["valid" => true];
}

function validateLink($input) {
    if (empty($input['section_id']) || !is_numeric($input['section_id'])) {
        return ["valid" => false, "error" => "Valid section_id is required"];
    }
    
    if (empty($input['link_name'])) {
        return ["valid" => false, "error" => "Link name is required"];
    }
    
    if (empty($input['link_url'])) {
        return ["valid" => false, "error" => "Link URL is required"];
    }
    
    if (strlen($input['link_name']) > 150) {
        return ["valid" => false, "error" => "Link name must be 150 characters or less"];
    }
    
    if (strlen($input['link_url']) > 500) {
        return ["valid" => false, "error" => "Link URL must be 500 characters or less"];
    }
    
    return ["valid" => true];
}

function validateBottomLink($input) {
    if (empty($input['link_name'])) {
        return ["valid" => false, "error" => "Link name is required"];
    }
    
    if (empty($input['link_url'])) {
        return ["valid" => false, "error" => "Link URL is required"];
    }
    
    if (strlen($input['link_name']) > 100) {
        return ["valid" => false, "error" => "Link name must be 100 characters or less"];
    }
    
    if (strlen($input['link_url']) > 255) {
        return ["valid" => false, "error" => "Link URL must be 255 characters or less"];
    }
    
    return ["valid" => true];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$endpoint = $_GET['endpoint'] ?? '';

$initResult = createTablesInCcetMaster($conn);
if (!$initResult["success"]) {
    echo json_encode($initResult);
    exit;
}

$conn->select_db('ccet_master');

switch ($method) {
    case 'GET':
        if ($endpoint === 'database-info') {
            $dbInfo = [
                "database" => "ccet_master",
                "footer_tables" => []
            ];
            
            $result = $conn->query("SHOW TABLES LIKE 'footer_%'");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $tableName = $row[0];
                    $countResult = $conn->query("SELECT COUNT(*) as count FROM `$tableName` WHERE is_active = 1");
                    $count = $countResult->fetch_assoc()['count'];
                    $dbInfo['footer_tables'][$tableName] = [
                        "name" => $tableName,
                        "record_count" => (int)$count
                    ];
                }
            }
            
            echo json_encode($dbInfo);
            break;
        }

        if ($endpoint === 'full-footer') {
            // Get complete footer structure from ccet_master database
            $query = "
                SELECT 
                    fs.id as section_id, fs.section_name, fs.section_order,
                    fl.id as link_id, fl.link_name, fl.link_url, fl.is_external, fl.link_order
                FROM footer_sections fs
                LEFT JOIN footer_links fl ON fs.id = fl.section_id AND fl.is_active = 1
                WHERE fs.is_active = 1
                ORDER BY fs.section_order, fl.link_order
            ";
            
            $result = $conn->query($query);
            if ($result) {
                $sections = [];
                while ($row = $result->fetch_assoc()) {
                    $sectionId = $row['section_id'];
                    
                    if (!isset($sections[$sectionId])) {
                        $sections[$sectionId] = [
                            'id' => $sectionId,
                            'section_name' => $row['section_name'],
                            'section_order' => $row['section_order'],
                            'links' => []
                        ];
                    }
                    
                    if ($row['link_id']) {
                        $sections[$sectionId]['links'][] = [
                            'id' => $row['link_id'],
                            'link_name' => $row['link_name'],
                            'link_url' => $row['link_url'],
                            'is_external' => (bool)$row['is_external'],
                            'link_order' => $row['link_order']
                        ];
                    }
                }
                
                // Get bottom footer links
                $bottomQuery = "SELECT * FROM footer_bottom_links WHERE is_active = 1 ORDER BY link_order";
                $bottomResult = $conn->query($bottomQuery);
                $bottomLinks = [];
                if ($bottomResult) {
                    while ($row = $bottomResult->fetch_assoc()) {
                        $bottomLinks[] = [
                            'id' => $row['id'],
                            'link_name' => $row['link_name'],
                            'link_url' => $row['link_url'],
                            'is_external' => (bool)$row['is_external'],
                            'link_order' => $row['link_order']
                        ];
                    }
                }
                
                echo json_encode([
                    "database" => "ccet_master",
                    "total_sections" => count($sections),
                    "total_bottom_links" => count($bottomLinks),
                    "sections" => array_values($sections),
                    "bottom_links" => $bottomLinks
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }
            break;
        }

        $table = '';
        $whereClauses = [];
        
        switch ($endpoint) {
            case 'sections':
                $table = 'footer_sections';
                if (!empty($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $whereClauses[] = "id = $id";
                }
                if (!empty($_GET['section_name'])) {
                    $name = $conn->real_escape_string($_GET['section_name']);
                    $whereClauses[] = "section_name LIKE '%$name%'";
                }
                break;
                
            case 'links':
                $table = 'footer_links';
                if (!empty($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $whereClauses[] = "id = $id";
                }
                if (!empty($_GET['section_id'])) {
                    $sectionId = (int)$_GET['section_id'];
                    $whereClauses[] = "section_id = $sectionId";
                }
                break;
                
            case 'bottom-links':
                $table = 'footer_bottom_links';
                if (!empty($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $whereClauses[] = "id = $id";
                }
                break;
                
            default:
                echo json_encode([
                    "success" => false, 
                    "error" => "Invalid endpoint. Use: sections, links, bottom-links, full-footer, or database-info"
                ]);
                exit;
        }
        
        if ($table) {
            $query = "SELECT * FROM `$table`";
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses) . " AND is_active = 1";
            } else {
                $query .= " WHERE is_active = 1";
            }
            
            if ($table === 'footer_sections') {
                $query .= " ORDER BY section_order, section_name";
            } elseif ($table === 'footer_links') {
                $query .= " ORDER BY link_order, link_name";
            } elseif ($table === 'footer_bottom_links') {
                $query .= " ORDER BY link_order, link_name";
            }
            
            $result = $conn->query($query);
            if ($result) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode([
                    "database" => "ccet_master",
                    "table" => $table,
                    "count" => count($data),
                    "data" => $data
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }
        }
        break;

    case 'POST':
        switch ($endpoint) {
            case 'sections':
                $validation = validateSection($input);
                if (!$validation['valid']) {
                    echo json_encode(["success" => false, "error" => $validation['error']]);
                    break;
                }
                
                $section_name = $conn->real_escape_string($input['section_name']);
                $section_order = isset($input['section_order']) ? (int)$input['section_order'] : 0;
                
                $sql = "INSERT INTO footer_sections (section_name, section_order) VALUES ('$section_name', $section_order)";
                break;
                
            case 'links':
                $validation = validateLink($input);
                if (!$validation['valid']) {
                    echo json_encode(["success" => false, "error" => $validation['error']]);
                    break;
                }
                
                $section_id = (int)$input['section_id'];
                $link_name = $conn->real_escape_string($input['link_name']);
                $link_url = $conn->real_escape_string($input['link_url']);
                $is_external = isset($input['is_external']) ? ((bool)$input['is_external'] ? 1 : 0) : 0;
                $link_order = isset($input['link_order']) ? (int)$input['link_order'] : 0;
                
                $sql = "INSERT INTO footer_links (section_id, link_name, link_url, is_external, link_order) VALUES ($section_id, '$link_name', '$link_url', $is_external, $link_order)";
                break;
                
            case 'bottom-links':
                $validation = validateBottomLink($input);
                if (!$validation['valid']) {
                    echo json_encode(["success" => false, "error" => $validation['error']]);
                    break;
                }
                
                $link_name = $conn->real_escape_string($input['link_name']);
                $link_url = $conn->real_escape_string($input['link_url']);
                $is_external = isset($input['is_external']) ? ((bool)$input['is_external'] ? 1 : 0) : 0;
                $link_order = isset($input['link_order']) ? (int)$input['link_order'] : 0;
                
                $sql = "INSERT INTO footer_bottom_links (link_name, link_url, is_external, link_order) VALUES ('$link_name', '$link_url', $is_external, $link_order)";
                break;
                
            default:
                echo json_encode(["success" => false, "error" => "Invalid endpoint for POST"]);
                exit;
        }
        
        if (isset($sql) && $conn->query($sql)) {
            echo json_encode([
                "success" => true, 
                "id" => $conn->insert_id,
                "database" => "ccet_master",
                "table" => "footer_" . str_replace('-', '_', $endpoint)
            ]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error ?? "SQL not defined"]);
        }
        break;

    case 'PATCH':
        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID parameter required for updates"]);
            break;
        }
        
        $id = (int)$_GET['id'];
        $updates = [];
        
        switch ($endpoint) {
            case 'sections':
                $allowedFields = ['section_name', 'section_order', 'is_active'];
                foreach ($input as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        if ($key === 'section_name' && empty($value)) {
                            echo json_encode(["success" => false, "error" => "Section name cannot be empty"]);
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
                $table = 'footer_sections';
                break;
                
            case 'links':
                $allowedFields = ['link_name', 'link_url', 'is_external', 'link_order', 'is_active'];
                foreach ($input as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        if (in_array($key, ['link_name', 'link_url']) && empty($value)) {
                            echo json_encode(["success" => false, "error" => ucfirst(str_replace('_', ' ', $key)) . " cannot be empty"]);
                            break 2;
                        }
                        if ($key === 'is_external') {
                            $updates[] = "$key = " . ((bool)$value ? 1 : 0);
                        } elseif ($value === null || $value === '') {
                            $updates[] = "$key = NULL";
                        } else {
                            $value = $conn->real_escape_string($value);
                            $updates[] = "$key = '$value'";
                        }
                    }
                }
                $table = 'footer_links';
                break;
                
            case 'bottom-links':
                $allowedFields = ['link_name', 'link_url', 'is_external', 'link_order', 'is_active'];
                foreach ($input as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        if (in_array($key, ['link_name', 'link_url']) && empty($value)) {
                            echo json_encode(["success" => false, "error" => ucfirst(str_replace('_', ' ', $key)) . " cannot be empty"]);
                            break 2;
                        }
                        if ($key === 'is_external') {
                            $updates[] = "$key = " . ((bool)$value ? 1 : 0);
                        } elseif ($value === null || $value === '') {
                            $updates[] = "$key = NULL";
                        } else {
                            $value = $conn->real_escape_string($value);
                            $updates[] = "$key = '$value'";
                        }
                    }
                }
                $table = 'footer_bottom_links';
                break;
                
            default:
                echo json_encode(["success" => false, "error" => "Invalid endpoint for PATCH"]);
                exit;
        }
        
        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }
        
        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE `$table` SET " . implode(", ", $updates) . " WHERE id = $id";
        
        if ($conn->query($sql)) {
            echo json_encode([
                "success" => true, 
                "updated_rows" => $conn->affected_rows,
                "database" => "ccet_master",
                "table" => $table
            ]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'DELETE':
        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID parameter required for deletion"]);
            break;
        }
        
        $id = (int)$_GET['id'];
        
        switch ($endpoint) {
            case 'sections':
                $table = 'footer_sections';
                break;
            case 'links':
                $table = 'footer_links';
                break;
            case 'bottom-links':
                $table = 'footer_bottom_links';
                break;
            default:
                echo json_encode(["success" => false, "error" => "Invalid endpoint for DELETE"]);
                exit;
        }
        
        $sql = "UPDATE `$table` SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = $id";
        
        if ($conn->query($sql)) {
            echo json_encode([
                "success" => true, 
                "deleted_rows" => $conn->affected_rows,
                "database" => "ccet_master",
                "table" => $table
            ]);
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