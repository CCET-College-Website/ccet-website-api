<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createPrivacyPolicy = "CREATE TABLE IF NOT EXISTS `privacy_policy` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT,
        subsection VARCHAR(100),
        icon VARCHAR(50),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        last_updated DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createPrivacyPolicy)) {
        return ["success" => false, "error" => "Failed to create privacy_policy table: " . $conn->error];
    }

    $createDefinitions = "CREATE TABLE IF NOT EXISTS `privacy_definitions` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        term VARCHAR(100) NOT NULL UNIQUE,
        definition TEXT NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createDefinitions)) {
        return ["success" => false, "error" => "Failed to create privacy_definitions table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_privacy_section ON `privacy_policy`(section)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_privacy_is_active ON `privacy_policy`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_privacy_order ON `privacy_policy`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_definitions_term ON `privacy_definitions`(term)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$table = isset($_GET['table']) ? $_GET['table'] : 'privacy_policy';
$allowedTables = ['privacy_policy', 'privacy_definitions'];

if (!in_array($table, $allowedTables)) {
    echo json_encode(["success" => false, "error" => "Invalid table specified"]);
    exit;
}

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            
            if ($table === 'privacy_policy') {
                $result = $conn->query("SELECT * FROM `$table` 
                                       WHERE title LIKE '%$keyword%' 
                                       OR content LIKE '%$keyword%'
                                       OR section LIKE '%$keyword%'
                                       OR subsection LIKE '%$keyword%'
                                       ORDER BY display_order ASC, section ASC");
            } else {
                $result = $conn->query("SELECT * FROM `$table` 
                                       WHERE term LIKE '%$keyword%' 
                                       OR definition LIKE '%$keyword%'
                                       ORDER BY display_order ASC, term ASC");
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

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            if ($table === 'privacy_policy') {
                if (!empty($_GET['section'])) {
                    $section = $conn->real_escape_string($_GET['section']);
                    $whereClauses[] = "section = '$section'";
                }
                if (!empty($_GET['subsection'])) {
                    $subsection = $conn->real_escape_string($_GET['subsection']);
                    $whereClauses[] = "subsection = '$subsection'";
                }
            } elseif ($table === 'privacy_definitions') {
                if (!empty($_GET['term'])) {
                    $term = $conn->real_escape_string($_GET['term']);
                    $whereClauses[] = "term = '$term'";
                }
            }

            $query = "SELECT * FROM `$table`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            $query .= " ORDER BY display_order ASC";

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

        if ($table === 'privacy_policy') {
            $requiredFields = ['section', 'title'];
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

            $section = $conn->real_escape_string($input['section']);
            $title = $conn->real_escape_string($input['title']);
            $content = !empty($input['content']) ? "'" . $conn->real_escape_string($input['content']) . "'" : "NULL";
            $subsection = !empty($input['subsection']) ? "'" . $conn->real_escape_string($input['subsection']) . "'" : "NULL";
            $icon = !empty($input['icon']) ? "'" . $conn->real_escape_string($input['icon']) . "'" : "NULL";
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $last_updated = !empty($input['last_updated']) ? "'" . $conn->real_escape_string($input['last_updated']) . "'" : "CURDATE()";

            $sql = "INSERT INTO `$table` (section, title, content, subsection, icon, display_order, is_active, last_updated) 
                    VALUES ('$section', '$title', $content, $subsection, $icon, $display_order, $is_active, $last_updated)";

            if ($conn->query($sql)) {
                $record_id = $conn->insert_id;
                echo json_encode([
                    "success" => true, 
                    "id" => $record_id,
                    "section" => $input['section'],
                    "title" => $input['title'],
                    "message" => "Privacy policy item added successfully"
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } elseif ($table === 'privacy_definitions') {
            if (empty($input['term']) || empty($input['definition'])) {
                echo json_encode(["success" => false, "error" => "Missing required fields: term, definition"]);
                break;
            }

            $term = $conn->real_escape_string($input['term']);
            $definition = $conn->real_escape_string($input['definition']);
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            $sql = "INSERT INTO `$table` (term, definition, display_order, is_active) 
                    VALUES ('$term', '$definition', $display_order, $is_active)";

            if ($conn->query($sql)) {
                echo json_encode([
                    "success" => true, 
                    "id" => $conn->insert_id,
                    "term" => $input['term'],
                    "message" => "Definition added successfully"
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
        if (!empty($_GET['section']) && $table === 'privacy_policy') {
            $section = $conn->real_escape_string($_GET['section']);
            $whereClauses[] = "section = '$section'";
        }
        if (!empty($_GET['term']) && $table === 'privacy_definitions') {
            $term = $conn->real_escape_string($_GET['term']);
            $whereClauses[] = "term = '$term'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($table === 'privacy_policy') {
                $whereClauses[] = "(title LIKE '%$keyword%' OR content LIKE '%$keyword%' OR section LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(term LIKE '%$keyword%' OR definition LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/section/term/keyword required)"]);
            break;
        }

        $allowedFields = $table === 'privacy_policy' 
            ? ['section', 'title', 'content', 'subsection', 'icon', 'display_order', 'is_active', 'last_updated']
            : ['term', 'definition', 'display_order', 'is_active'];
        
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_active' || $key === 'display_order') {
                    $updates[] = "$key = " . (int)$value;
                    continue;
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
        $sql = "UPDATE `$table` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['section']) && $table === 'privacy_policy') {
            $section = $conn->real_escape_string($_GET['section']);
            $whereClauses[] = "section = '$section'";
        }
        if (!empty($_GET['term']) && $table === 'privacy_definitions') {
            $term = $conn->real_escape_string($_GET['term']);
            $whereClauses[] = "term = '$term'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($table === 'privacy_policy') {
                $whereClauses[] = "(title LIKE '%$keyword%' OR content LIKE '%$keyword%' OR section LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(term LIKE '%$keyword%' OR definition LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/section/term/keyword required)"]);
            break;
        }

        $sql = "UPDATE `$table` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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