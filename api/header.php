<?php
include '../server.php';

header("Access-Control-Allow-Origin: http://localhost:5173");

function createTablesInCcetMaster($conn) {
    // Step 1: Select the ccet_master database (assuming it already exists)
    if (!$conn->select_db('ccet_master')) {
        return ["success" => false, "error" => "Failed to select ccet_master database: " . $conn->error];
    }
    
    // Step 2: Create all 4 header navigation tables in ccet_master database
    
    // Table 1: Main navigation items
    $createNavItems = "CREATE TABLE IF NOT EXISTS `header_nav_items` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nav_name VARCHAR(100) NOT NULL UNIQUE,
        nav_path VARCHAR(255) NULL,
        nav_order INT NOT NULL DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_nav_order (nav_order, is_active),
        INDEX idx_nav_name (nav_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Table 2: Submenus under navigation items
    $createSubmenus = "CREATE TABLE IF NOT EXISTS `header_nav_submenus` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nav_id INT NOT NULL,
        submenu_name VARCHAR(100) NOT NULL,
        submenu_order INT NOT NULL DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (nav_id) REFERENCES header_nav_items(id) ON DELETE CASCADE ON UPDATE CASCADE,
        UNIQUE KEY unique_nav_submenu (nav_id, submenu_name),
        INDEX idx_nav_submenu (nav_id, submenu_order, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Table 3: Navigation tabs/links under submenus
    $createNavTabs = "CREATE TABLE IF NOT EXISTS `header_nav_tabs` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submenu_id INT NOT NULL,
        tab_name VARCHAR(150) NOT NULL,
        tab_path VARCHAR(255) NULL,
        is_external BOOLEAN DEFAULT FALSE,
        tab_order INT NOT NULL DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (submenu_id) REFERENCES header_nav_submenus(id) ON DELETE CASCADE ON UPDATE CASCADE,
        INDEX idx_submenu_tab (submenu_id, tab_order, is_active),
        INDEX idx_tab_name (tab_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Table 4: PDF documents under navigation tabs
    $createNavPdfs = "CREATE TABLE IF NOT EXISTS `header_nav_pdfs` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tab_id INT NOT NULL,
        pdf_name VARCHAR(200) NOT NULL,
        pdf_link VARCHAR(500) NOT NULL,
        pdf_order INT NOT NULL DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (tab_id) REFERENCES header_nav_tabs(id) ON DELETE CASCADE ON UPDATE CASCADE,
        INDEX idx_tab_pdf (tab_id, pdf_order, is_active),
        INDEX idx_pdf_name (pdf_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Execute table creation queries
    $tables = [
        'header_nav_items' => $createNavItems,
        'header_nav_submenus' => $createSubmenus, 
        'header_nav_tabs' => $createNavTabs,
        'header_nav_pdfs' => $createNavPdfs
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
        "message" => "Header navigation tables created successfully in ccet_master database",
        "database" => "ccet_master",
        "tables_created" => $createdTables,
        "table_count" => count($createdTables)
    ];
}

function validateNavItem($input) {
    if (empty($input['nav_name'])) {
        return ["valid" => false, "error" => "Nav name is required"];
    }
    
    if (strlen($input['nav_name']) > 100) {
        return ["valid" => false, "error" => "Nav name must be 100 characters or less"];
    }
    
    return ["valid" => true];
}

function validateSubmenu($input) {
    if (empty($input['nav_id']) || !is_numeric($input['nav_id'])) {
        return ["valid" => false, "error" => "Valid nav_id is required"];
    }
    
    if (empty($input['submenu_name'])) {
        return ["valid" => false, "error" => "Submenu name is required"];
    }
    
    if (strlen($input['submenu_name']) > 100) {
        return ["valid" => false, "error" => "Submenu name must be 100 characters or less"];
    }
    
    return ["valid" => true];
}

function validateTab($input) {
    if (empty($input['submenu_id']) || !is_numeric($input['submenu_id'])) {
        return ["valid" => false, "error" => "Valid submenu_id is required"];
    }
    
    if (empty($input['tab_name'])) {
        return ["valid" => false, "error" => "Tab name is required"];
    }
    
    if (strlen($input['tab_name']) > 150) {
        return ["valid" => false, "error" => "Tab name must be 150 characters or less"];
    }
    
    return ["valid" => true];
}

function validatePdf($input) {
    if (empty($input['tab_id']) || !is_numeric($input['tab_id'])) {
        return ["valid" => false, "error" => "Valid tab_id is required"];
    }
    
    if (empty($input['pdf_name'])) {
        return ["valid" => false, "error" => "PDF name is required"];
    }
    
    if (empty($input['pdf_link'])) {
        return ["valid" => false, "error" => "PDF link is required"];
    }
    
    if (strlen($input['pdf_name']) > 200) {
        return ["valid" => false, "error" => "PDF name must be 200 characters or less"];
    }
    
    if (strlen($input['pdf_link']) > 500) {
        return ["valid" => false, "error" => "PDF link must be 500 characters or less"];
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
                "header_tables" => []
            ];
            
            $result = $conn->query("SHOW TABLES LIKE 'header_%'");
            if ($result) {
                while ($row = $result->fetch_array()) {
                    $tableName = $row[0];
                    $countResult = $conn->query("SELECT COUNT(*) as count FROM `$tableName` WHERE is_active = 1");
                    $count = $countResult->fetch_assoc()['count'];
                    $dbInfo['header_tables'][$tableName] = [
                        "name" => $tableName,
                        "record_count" => (int)$count
                    ];
                }
            }
            
            echo json_encode($dbInfo);
            break;
        }

        if ($endpoint === 'full-navigation') {
            // Get complete navigation structure from ccet_master database
            $query = "
                SELECT 
                    ni.id as nav_id, ni.nav_name, ni.nav_path, ni.nav_order,
                    ns.id as submenu_id, ns.submenu_name, ns.submenu_order,
                    nt.id as tab_id, nt.tab_name, nt.tab_path, nt.is_external, nt.tab_order,
                    np.id as pdf_id, np.pdf_name, np.pdf_link, np.pdf_order
                FROM header_nav_items ni
                LEFT JOIN header_nav_submenus ns ON ni.id = ns.nav_id AND ns.is_active = 1
                LEFT JOIN header_nav_tabs nt ON ns.id = nt.submenu_id AND nt.is_active = 1
                LEFT JOIN header_nav_pdfs np ON nt.id = np.tab_id AND np.is_active = 1
                WHERE ni.is_active = 1
                ORDER BY ni.nav_order, ns.submenu_order, nt.tab_order, np.pdf_order
            ";
            
            $result = $conn->query($query);
            if ($result) {
                $navigation = [];
                while ($row = $result->fetch_assoc()) {
                    $navId = $row['nav_id'];
                    $submenuId = $row['submenu_id'];
                    $tabId = $row['tab_id'];
                    
                    if (!isset($navigation[$navId])) {
                        $navigation[$navId] = [
                            'id' => $navId,
                            'nav_name' => $row['nav_name'],
                            'nav_path' => $row['nav_path'],
                            'nav_order' => $row['nav_order'],
                            'submenus' => []
                        ];
                    }
                    
                    if ($submenuId && !isset($navigation[$navId]['submenus'][$submenuId])) {
                        $navigation[$navId]['submenus'][$submenuId] = [
                            'id' => $submenuId,
                            'submenu_name' => $row['submenu_name'],
                            'submenu_order' => $row['submenu_order'],
                            'tabs' => []
                        ];
                    }
                    
                    if ($tabId && !isset($navigation[$navId]['submenus'][$submenuId]['tabs'][$tabId])) {
                        $navigation[$navId]['submenus'][$submenuId]['tabs'][$tabId] = [
                            'id' => $tabId,
                            'tab_name' => $row['tab_name'],
                            'tab_path' => $row['tab_path'],
                            'is_external' => (bool)$row['is_external'],
                            'tab_order' => $row['tab_order'],
                            'pdfs' => []
                        ];
                    }
                    
                    if ($row['pdf_id']) {
                        $navigation[$navId]['submenus'][$submenuId]['tabs'][$tabId]['pdfs'][] = [
                            'id' => $row['pdf_id'],
                            'pdf_name' => $row['pdf_name'],
                            'pdf_link' => $row['pdf_link'],
                            'pdf_order' => $row['pdf_order']
                        ];
                    }
                }
                
                foreach ($navigation as &$nav) {
                    $nav['submenus'] = array_values(array_filter($nav['submenus'], function($submenu) {
                        return !empty($submenu['submenu_name']);
                    }));
                    
                    foreach ($nav['submenus'] as &$submenu) {
                        $submenu['tabs'] = array_values(array_filter($submenu['tabs'], function($tab) {
                            return !empty($tab['tab_name']);
                        }));
                    }
                }
                
                echo json_encode([
                    "database" => "ccet_master",
                    "total_nav_items" => count($navigation),
                    "navigation" => array_values($navigation)
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }
            break;
        }

        $table = '';
        $whereClauses = [];
        
        switch ($endpoint) {
            case 'nav-items':
                $table = 'header_nav_items';
                if (!empty($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $whereClauses[] = "id = $id";
                }
                if (!empty($_GET['nav_name'])) {
                    $name = $conn->real_escape_string($_GET['nav_name']);
                    $whereClauses[] = "nav_name LIKE '%$name%'";
                }
                break;
                
            case 'submenus':
                $table = 'header_nav_submenus';
                if (!empty($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $whereClauses[] = "id = $id";
                }
                if (!empty($_GET['nav_id'])) {
                    $navId = (int)$_GET['nav_id'];
                    $whereClauses[] = "nav_id = $navId";
                }
                break;
                
            case 'tabs':
                $table = 'header_nav_tabs';
                if (!empty($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $whereClauses[] = "id = $id";
                }
                if (!empty($_GET['submenu_id'])) {
                    $submenuId = (int)$_GET['submenu_id'];
                    $whereClauses[] = "submenu_id = $submenuId";
                }
                break;
                
            case 'pdfs':
                $table = 'header_nav_pdfs';
                if (!empty($_GET['id'])) {
                    $id = (int)$_GET['id'];
                    $whereClauses[] = "id = $id";
                }
                if (!empty($_GET['tab_id'])) {
                    $tabId = (int)$_GET['tab_id'];
                    $whereClauses[] = "tab_id = $tabId";
                }
                break;
                
            default:
                echo json_encode([
                    "success" => false, 
                    "error" => "Invalid endpoint. Use: nav-items, submenus, tabs, pdfs, full-navigation, or database-info"
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
            
            if ($table === 'header_nav_items') {
                $query .= " ORDER BY nav_order, nav_name";
            } elseif ($table === 'header_nav_submenus') {
                $query .= " ORDER BY submenu_order, submenu_name";
            } elseif ($table === 'header_nav_tabs') {
                $query .= " ORDER BY tab_order, tab_name";
            } elseif ($table === 'header_nav_pdfs') {
                $query .= " ORDER BY pdf_order, pdf_name";
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
            case 'nav-items':
                $validation = validateNavItem($input);
                if (!$validation['valid']) {
                    echo json_encode(["success" => false, "error" => $validation['error']]);
                    break;
                }
                
                $nav_name = $conn->real_escape_string($input['nav_name']);
                $nav_path = isset($input['nav_path']) ? "'" . $conn->real_escape_string($input['nav_path']) . "'" : 'NULL';
                $nav_order = isset($input['nav_order']) ? (int)$input['nav_order'] : 0;
                
                $sql = "INSERT INTO header_nav_items (nav_name, nav_path, nav_order) VALUES ('$nav_name', $nav_path, $nav_order)";
                break;
                
            case 'submenus':
                $validation = validateSubmenu($input);
                if (!$validation['valid']) {
                    echo json_encode(["success" => false, "error" => $validation['error']]);
                    break;
                }
                
                $nav_id = (int)$input['nav_id'];
                $submenu_name = $conn->real_escape_string($input['submenu_name']);
                $submenu_order = isset($input['submenu_order']) ? (int)$input['submenu_order'] : 0;
                
                $sql = "INSERT INTO header_nav_submenus (nav_id, submenu_name, submenu_order) VALUES ($nav_id, '$submenu_name', $submenu_order)";
                break;
                
            case 'tabs':
                $validation = validateTab($input);
                if (!$validation['valid']) {
                    echo json_encode(["success" => false, "error" => $validation['error']]);
                    break;
                }
                
                $submenu_id = (int)$input['submenu_id'];
                $tab_name = $conn->real_escape_string($input['tab_name']);
                $tab_path = isset($input['tab_path']) ? "'" . $conn->real_escape_string($input['tab_path']) . "'" : 'NULL';
                $is_external = isset($input['is_external']) ? ((bool)$input['is_external'] ? 1 : 0) : 0;
                $tab_order = isset($input['tab_order']) ? (int)$input['tab_order'] : 0;
                
                $sql = "INSERT INTO header_nav_tabs (submenu_id, tab_name, tab_path, is_external, tab_order) VALUES ($submenu_id, '$tab_name', $tab_path, $is_external, $tab_order)";
                break;
                
            case 'pdfs':
                $validation = validatePdf($input);
                if (!$validation['valid']) {
                    echo json_encode(["success" => false, "error" => $validation['error']]);
                    break;
                }
                
                $tab_id = (int)$input['tab_id'];
                $pdf_name = $conn->real_escape_string($input['pdf_name']);
                $pdf_link = $conn->real_escape_string($input['pdf_link']);
                $pdf_order = isset($input['pdf_order']) ? (int)$input['pdf_order'] : 0;
                
                $sql = "INSERT INTO header_nav_pdfs (tab_id, pdf_name, pdf_link, pdf_order) VALUES ($tab_id, '$pdf_name', '$pdf_link', $pdf_order)";
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
                "table" => "header_" . str_replace('-', '_', $endpoint)
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
            case 'nav-items':
                $allowedFields = ['nav_name', 'nav_path', 'nav_order', 'is_active'];
                foreach ($input as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        if ($key === 'nav_name' && empty($value)) {
                            echo json_encode(["success" => false, "error" => "Nav name cannot be empty"]);
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
                $table = 'header_nav_items';
                break;
                
            case 'submenus':
                $allowedFields = ['submenu_name', 'submenu_order', 'is_active'];
                foreach ($input as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        if ($key === 'submenu_name' && empty($value)) {
                            echo json_encode(["success" => false, "error" => "Submenu name cannot be empty"]);
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
                $table = 'header_nav_submenus';
                break;
                
            case 'tabs':
                $allowedFields = ['tab_name', 'tab_path', 'is_external', 'tab_order', 'is_active'];
                foreach ($input as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        if ($key === 'tab_name' && empty($value)) {
                            echo json_encode(["success" => false, "error" => "Tab name cannot be empty"]);
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
                $table = 'header_nav_tabs';
                break;
                
            case 'pdfs':
                $allowedFields = ['pdf_name', 'pdf_link', 'pdf_order', 'is_active'];
                foreach ($input as $key => $value) {
                    if (in_array($key, $allowedFields)) {
                        if (in_array($key, ['pdf_name', 'pdf_link']) && empty($value)) {
                            echo json_encode(["success" => false, "error" => ucfirst(str_replace('_', ' ', $key)) . " cannot be empty"]);
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
                $table = 'header_nav_pdfs';
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
            case 'nav-items':
                $table = 'header_nav_items';
                break;
            case 'submenus':
                $table = 'header_nav_submenus';
                break;
            case 'tabs':
                $table = 'header_nav_tabs';
                break;
            case 'pdfs':
                $table = 'header_nav_pdfs';
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