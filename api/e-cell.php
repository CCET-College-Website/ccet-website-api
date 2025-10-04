<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createEcellIncharge = "CREATE TABLE IF NOT EXISTS `ecell_incharge` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        position VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createStudentHelpdesk = "CREATE TABLE IF NOT EXISTS `student_helpdesk` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        year VARCHAR(50) NOT NULL,
        department VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createClassIncharge = "CREATE TABLE IF NOT EXISTS `class_incharge` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year VARCHAR(50) NOT NULL,
        branch VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        position VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createClassRepresentative = "CREATE TABLE IF NOT EXISTS `class_representative` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year VARCHAR(50) NOT NULL,
        branch VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        position VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        email VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createEcellIncharge)) {
        return ["success" => false, "error" => "Failed to create ecell_incharge table: " . $conn->error];
    }

    if (!$conn->query($createStudentHelpdesk)) {
        return ["success" => false, "error" => "Failed to create student_helpdesk table: " . $conn->error];
    }

    if (!$conn->query($createClassIncharge)) {
        return ["success" => false, "error" => "Failed to create class_incharge table: " . $conn->error];
    }

    if (!$conn->query($createClassRepresentative)) {
        return ["success" => false, "error" => "Failed to create class_representative table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_incharge_is_active ON `ecell_incharge`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_incharge_order ON `ecell_incharge`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_helpdesk_is_active ON `student_helpdesk`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_helpdesk_order ON `student_helpdesk`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_incharge_year_branch ON `class_incharge`(year, branch)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_incharge_is_active ON `class_incharge`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_cr_year_branch ON `class_representative`(year, branch)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_cr_is_active ON `class_representative`(is_active)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$table = isset($_GET['table']) ? $_GET['table'] : 'ecell_incharge';

if (!in_array($table, ['ecell_incharge', 'student_helpdesk', 'class_incharge', 'class_representative'])) {
    echo json_encode(["success" => false, "error" => "Invalid table. Use 'ecell_incharge', 'student_helpdesk', 'class_incharge', or 'class_representative'"]);
    exit;
}

$tableName = $table;

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (isset($_GET['grouped']) && $_GET['grouped'] === 'true') {
            $inchargeResult = $conn->query("SELECT * FROM `ecell_incharge` WHERE is_active = TRUE ORDER BY display_order ASC LIMIT 1");
            $ecellIncharge = $inchargeResult && $inchargeResult->num_rows > 0 ? $inchargeResult->fetch_assoc() : null;

            $helpdeskResult = $conn->query("SELECT * FROM `student_helpdesk` WHERE is_active = TRUE ORDER BY display_order ASC");
            $studentHelpdesk = $helpdeskResult && $helpdeskResult->num_rows > 0 ? $helpdeskResult->fetch_all(MYSQLI_ASSOC) : [];

            $yearsResult = $conn->query("SELECT DISTINCT year FROM `class_incharge` WHERE is_active = TRUE ORDER BY display_order ASC");
            $yearsData = [];

            if ($yearsResult) {
                while ($yearRow = $yearsResult->fetch_assoc()) {
                    $year = $yearRow['year'];
                    $yearsData[$year] = [];

                    $branchesResult = $conn->query("SELECT DISTINCT branch FROM `class_incharge` WHERE year = '$year' AND is_active = TRUE ORDER BY display_order ASC");
                    
                    if ($branchesResult) {
                        while ($branchRow = $branchesResult->fetch_assoc()) {
                            $branch = $branchRow['branch'];
                            
                            $inchargeQuery = "SELECT * FROM `class_incharge` WHERE year = '$year' AND branch = '$branch' AND is_active = TRUE ORDER BY display_order ASC LIMIT 1";
                            $inchargeData = $conn->query($inchargeQuery);
                            $incharge = $inchargeData && $inchargeData->num_rows > 0 ? $inchargeData->fetch_assoc() : null;

                            $crQuery = "SELECT * FROM `class_representative` WHERE year = '$year' AND branch = '$branch' AND is_active = TRUE ORDER BY display_order ASC";
                            $crData = $conn->query($crQuery);
                            $crs = $crData && $crData->num_rows > 0 ? $crData->fetch_all(MYSQLI_ASSOC) : [];

                            $yearsData[$year][$branch] = [
                                'incharge' => $incharge,
                                'crs' => $crs
                            ];
                        }
                    }
                }
            }

            echo json_encode([
                "ecell_incharge" => $ecellIncharge,
                "student_helpdesk" => $studentHelpdesk,
                "years_data" => $yearsData
            ]);
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            
            $result = $conn->query("SELECT * FROM `$tableName` 
                                   WHERE name LIKE '%$keyword%' 
                                   OR position LIKE '%$keyword%'
                                   OR email LIKE '%$keyword%'
                                   OR phone LIKE '%$keyword%'
                                   " . ($table === 'class_incharge' || $table === 'class_representative' ? "OR year LIKE '%$keyword%' OR branch LIKE '%$keyword%'" : "") . "
                                   ORDER BY display_order ASC, id ASC");
            
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

            if (!empty($_GET['year']) && ($table === 'class_incharge' || $table === 'class_representative')) {
                $year = $conn->real_escape_string($_GET['year']);
                $whereClauses[] = "year = '$year'";
            }

            if (!empty($_GET['branch']) && ($table === 'class_incharge' || $table === 'class_representative')) {
                $branch = $conn->real_escape_string($_GET['branch']);
                $whereClauses[] = "branch = '$branch'";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM `$tableName`";
            
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

        $requiredFields = ['name'];
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

        if ($table === 'student_helpdesk' && (empty($input['year']) || empty($input['department']))) {
            echo json_encode(["success" => false, "error" => "Missing required fields: year and department"]);
            break;
        }

        if (($table === 'class_incharge' || $table === 'class_representative') && (empty($input['year']) || empty($input['branch']))) {
            echo json_encode(["success" => false, "error" => "Missing required fields: year and branch"]);
            break;
        }

        $name = $conn->real_escape_string($input['name']);
        $position = !empty($input['position']) ? "'" . $conn->real_escape_string($input['position']) . "'" : "NULL";
        $phone = !empty($input['phone']) ? "'" . $conn->real_escape_string($input['phone']) . "'" : "NULL";
        $email = !empty($input['email']) ? "'" . $conn->real_escape_string($input['email']) . "'" : "NULL";
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

        if (!empty($input['email']) && !isValidEmail($input['email'])) {
            echo json_encode(["success" => false, "error" => "Invalid email format"]);
            break;
        }

        if ($table === 'ecell_incharge') {
            $sql = "INSERT INTO `$tableName` (name, position, phone, email, is_active, display_order) 
                    VALUES ('$name', $position, $phone, $email, $is_active, $display_order)";
        } else if ($table === 'student_helpdesk') {
            $year = $conn->real_escape_string($input['year']);
            $department = $conn->real_escape_string($input['department']);
            $sql = "INSERT INTO `$tableName` (name, year, department, phone, email, is_active, display_order) 
                    VALUES ('$name', '$year', '$department', $phone, $email, $is_active, $display_order)";
        } else {
            $year = $conn->real_escape_string($input['year']);
            $branch = $conn->real_escape_string($input['branch']);
            $sql = "INSERT INTO `$tableName` (name, year, branch, position, phone, email, is_active, display_order) 
                    VALUES ('$name', '$year', '$branch', $position, $phone, $email, $is_active, $display_order)";
        }

        if ($conn->query($sql)) {
            $record_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $record_id,
                "message" => ucfirst(str_replace('_', ' ', $table)) . " added successfully"
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
            $whereClauses[] = "(name LIKE '%$keyword%' OR position LIKE '%$keyword%' OR email LIKE '%$keyword%' OR phone LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        $allowedFields = ['name', 'position', 'phone', 'email', 'is_active', 'display_order'];
        
        if ($table === 'student_helpdesk') {
            $allowedFields[] = 'year';
            $allowedFields[] = 'department';
        } else if ($table === 'class_incharge' || $table === 'class_representative') {
            $allowedFields[] = 'year';
            $allowedFields[] = 'branch';
        }
        
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_active' || $key === 'display_order') {
                    $updates[] = "$key = " . (int)$value;
                    continue;
                }

                if ($key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
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
            $whereClauses[] = "(name LIKE '%$keyword%' OR position LIKE '%$keyword%' OR email LIKE '%$keyword%' OR phone LIKE '%$keyword%')";
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