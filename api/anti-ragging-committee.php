<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createCommittee = "CREATE TABLE IF NOT EXISTS `anti_ragging_committee` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        committee_type VARCHAR(100) NOT NULL,
        member_no INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        designation VARCHAR(255) NOT NULL,
        contact VARCHAR(50) NOT NULL,
        email VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createRaggingForms = "CREATE TABLE IF NOT EXISTS `anti_ragging_forms` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_description TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createPunishments = "CREATE TABLE IF NOT EXISTS `anti_ragging_punishments` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        punishment_description TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createHelplines = "CREATE TABLE IF NOT EXISTS `anti_ragging_helplines` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(255) NOT NULL,
        detail VARCHAR(255) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createCommittee)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_committee table: " . $conn->error];
    }

    if (!$conn->query($createRaggingForms)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_forms table: " . $conn->error];
    }

    if (!$conn->query($createPunishments)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_punishments table: " . $conn->error];
    }

    if (!$conn->query($createHelplines)) {
        return ["success" => false, "error" => "Failed to create anti_ragging_helplines table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_committee_type ON `anti_ragging_committee`(committee_type)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_committee_active ON `anti_ragging_committee`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_committee_order ON `anti_ragging_committee`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_forms_active ON `anti_ragging_forms`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_forms_order ON `anti_ragging_forms`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_punishments_active ON `anti_ragging_punishments`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_punishments_order ON `anti_ragging_punishments`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_helplines_active ON `anti_ragging_helplines`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_helplines_order ON `anti_ragging_helplines`(display_order)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Determine which table to operate on
$table = isset($_GET['table']) ? $_GET['table'] : 'committee';

if (!in_array($table, ['committee', 'forms', 'punishments', 'helplines'])) {
    echo json_encode(["success" => false, "error" => "Invalid table. Use 'committee', 'forms', 'punishments', or 'helplines'"]);
    exit;
}

$tableName = 'anti_ragging_' . $table;

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (isset($_GET['grouped']) && $_GET['grouped'] === 'true') {
            $committeeResult = $conn->query("SELECT * FROM `anti_ragging_committee` WHERE is_active = TRUE ORDER BY committee_type ASC, display_order ASC, member_no ASC");
            $committeeData = [];
            
            if ($committeeResult && $committeeResult->num_rows > 0) {
                while ($row = $committeeResult->fetch_assoc()) {
                    $type = $row['committee_type'];
                    if (!isset($committeeData[$type])) {
                        $committeeData[$type] = [];
                    }
                    $committeeData[$type][] = $row;
                }
            }

            $formsResult = $conn->query("SELECT * FROM `anti_ragging_forms` WHERE is_active = TRUE ORDER BY display_order ASC");
            $forms = $formsResult && $formsResult->num_rows > 0 ? $formsResult->fetch_all(MYSQLI_ASSOC) : [];

            $punishmentsResult = $conn->query("SELECT * FROM `anti_ragging_punishments` WHERE is_active = TRUE ORDER BY display_order ASC");
            $punishments = $punishmentsResult && $punishmentsResult->num_rows > 0 ? $punishmentsResult->fetch_all(MYSQLI_ASSOC) : [];

            $helplinesResult = $conn->query("SELECT * FROM `anti_ragging_helplines` WHERE is_active = TRUE ORDER BY display_order ASC");
            $helplines = $helplinesResult && $helplinesResult->num_rows > 0 ? $helplinesResult->fetch_all(MYSQLI_ASSOC) : [];

            echo json_encode([
                "committee" => $committeeData,
                "forms" => $forms,
                "punishments" => $punishments,
                "helplines" => $helplines
            ]);
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            
            if ($table === 'committee') {
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE name LIKE '%$keyword%' 
                                       OR designation LIKE '%$keyword%'
                                       OR committee_type LIKE '%$keyword%'
                                       OR contact LIKE '%$keyword%'
                                       OR email LIKE '%$keyword%'
                                       ORDER BY committee_type ASC, display_order ASC, member_no ASC");
            } else {
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE form_description LIKE '%$keyword%' 
                                       OR punishment_description LIKE '%$keyword%'
                                       OR label LIKE '%$keyword%'
                                       OR detail LIKE '%$keyword%'
                                       ORDER BY display_order ASC, id ASC");
            }
            
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No records found with that keyword"]);
            }
        } else {
            $whereClauses = ["is_active = TRUE"];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['type']) && $table === 'committee') {
                $type = $conn->real_escape_string($_GET['type']);
                $whereClauses[] = "committee_type = '$type'";
            }

            $query = "SELECT * FROM `$tableName`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            if ($table === 'committee') {
                $query .= " ORDER BY committee_type ASC, display_order ASC, member_no ASC";
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

        if ($table === 'committee') {
            $requiredFields = ['committee_type', 'member_no', 'name', 'designation', 'contact'];
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

            $committee_type = $conn->real_escape_string($input['committee_type']);
            $member_no = (int)$input['member_no'];
            $name = $conn->real_escape_string($input['name']);
            $designation = $conn->real_escape_string($input['designation']);
            $contact = $conn->real_escape_string($input['contact']);
            $email = !empty($input['email']) ? "'" . $conn->real_escape_string($input['email']) . "'" : "NULL";
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            $sql = "INSERT INTO `$tableName` (committee_type, member_no, name, designation, contact, email, is_active, display_order) 
                    VALUES ('$committee_type', $member_no, '$name', '$designation', '$contact', $email, $is_active, $display_order)";

        } else if ($table === 'forms') {
            if (empty($input['form_description'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: form_description"]);
                break;
            }

            $form_description = $conn->real_escape_string($input['form_description']);
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            $sql = "INSERT INTO `$tableName` (form_description, is_active, display_order) 
                    VALUES ('$form_description', $is_active, $display_order)";

        } else if ($table === 'punishments') {
            if (empty($input['punishment_description'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: punishment_description"]);
                break;
            }

            $punishment_description = $conn->real_escape_string($input['punishment_description']);
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            $sql = "INSERT INTO `$tableName` (punishment_description, is_active, display_order) 
                    VALUES ('$punishment_description', $is_active, $display_order)";

        } else { // helplines
            $requiredFields = ['label', 'detail'];
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

            $label = $conn->real_escape_string($input['label']);
            $detail = $conn->real_escape_string($input['detail']);
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            $sql = "INSERT INTO `$tableName` (label, detail, is_active, display_order) 
                    VALUES ('$label', '$detail', $is_active, $display_order)";
        }

        if ($conn->query($sql)) {
            $record_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $record_id,
                "message" => ucfirst($table) . " added successfully"
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
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($table === 'committee') {
                $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR committee_type LIKE '%$keyword%')";
            } else if ($table === 'forms') {
                $whereClauses[] = "(form_description LIKE '%$keyword%')";
            } else if ($table === 'punishments') {
                $whereClauses[] = "(punishment_description LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(label LIKE '%$keyword%' OR detail LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        if ($table === 'committee') {
            $allowedFields = ['committee_type', 'member_no', 'name', 'designation', 'contact', 'email', 'is_active', 'display_order'];
        } else if ($table === 'forms') {
            $allowedFields = ['form_description', 'is_active', 'display_order'];
        } else if ($table === 'punishments') {
            $allowedFields = ['punishment_description', 'is_active', 'display_order'];
        } else {
            $allowedFields = ['label', 'detail', 'is_active', 'display_order'];
        }
        
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_active' || $key === 'display_order' || $key === 'member_no') {
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
        $sql = "UPDATE `$tableName` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($table === 'committee') {
                $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR committee_type LIKE '%$keyword%')";
            } else if ($table === 'forms') {
                $whereClauses[] = "(form_description LIKE '%$keyword%')";
            } else if ($table === 'punishments') {
                $whereClauses[] = "(punishment_description LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(label LIKE '%$keyword%' OR detail LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        $sql = "UPDATE `$tableName` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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