<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createDepartments = "CREATE TABLE IF NOT EXISTS `nba_departments` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(10) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createCourseFiles = "CREATE TABLE IF NOT EXISTS `nba_course_files` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(10) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_url VARCHAR(500) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_code) REFERENCES nba_departments(code) ON DELETE CASCADE
    )";
    
    $createResources = "CREATE TABLE IF NOT EXISTS `nba_resources` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(10) NOT NULL,
        resource_title VARCHAR(255) NOT NULL,
        resource_url VARCHAR(500) NOT NULL,
        category VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_code) REFERENCES nba_departments(code) ON DELETE CASCADE
    )";

    if (!$conn->query($createDepartments)) {
        return ["success" => false, "error" => "Failed to create nba_departments table: " . $conn->error];
    }

    if (!$conn->query($createCourseFiles)) {
        return ["success" => false, "error" => "Failed to create nba_course_files table: " . $conn->error];
    }

    if (!$conn->query($createResources)) {
        return ["success" => false, "error" => "Failed to create nba_resources table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_dept_code ON `nba_departments`(code)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_dept_active ON `nba_departments`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_course_dept ON `nba_course_files`(department_code)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_course_active ON `nba_course_files`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_resources_dept ON `nba_resources`(department_code)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_resources_active ON `nba_resources`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_resources_category ON `nba_resources`(category)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Determine which table to operate on
$table = isset($_GET['table']) ? $_GET['table'] : 'departments';

if (!in_array($table, ['departments', 'course_files', 'resources'])) {
    echo json_encode(["success" => false, "error" => "Invalid table. Use 'departments', 'course_files', or 'resources'"]);
    exit;
}

$tableName = 'nba_' . $table;

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (isset($_GET['grouped']) && $_GET['grouped'] === 'true') {
            $department_code = isset($_GET['department']) ? $conn->real_escape_string($_GET['department']) : 'CSE';
            
            $deptResult = $conn->query("SELECT * FROM `nba_departments` WHERE code = '$department_code' AND is_active = TRUE LIMIT 1");
            $department = $deptResult && $deptResult->num_rows > 0 ? $deptResult->fetch_assoc() : null;

            if (!$department) {
                echo json_encode(["success" => false, "error" => "Department not found"]);
                break;
            }

            $courseFilesResult = $conn->query("SELECT * FROM `nba_course_files` WHERE department_code = '$department_code' AND is_active = TRUE ORDER BY display_order ASC, course_name ASC");
            $courseFiles = $courseFilesResult && $courseFilesResult->num_rows > 0 ? $courseFilesResult->fetch_all(MYSQLI_ASSOC) : [];

            $resourcesResult = $conn->query("SELECT * FROM `nba_resources` WHERE department_code = '$department_code' AND is_active = TRUE ORDER BY display_order ASC, resource_title ASC");
            $resources = $resourcesResult && $resourcesResult->num_rows > 0 ? $resourcesResult->fetch_all(MYSQLI_ASSOC) : [];

            echo json_encode([
                "department" => $department,
                "course_files" => $courseFiles,
                "resources" => $resources
            ]);
            break;
        }

        if ($table === 'departments' && !isset($_GET['department']) && !isset($_GET['keyword'])) {
            $result = $conn->query("SELECT * FROM `$tableName` WHERE is_active = TRUE ORDER BY display_order ASC, name ASC");
            if ($result) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            
            if ($table === 'departments') {
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE name LIKE '%$keyword%' 
                                       OR full_name LIKE '%$keyword%'
                                       OR code LIKE '%$keyword%'
                                       ORDER BY display_order ASC, name ASC");
            } else if ($table === 'course_files') {
                $deptFilter = isset($_GET['department']) ? "AND department_code = '" . $conn->real_escape_string($_GET['department']) . "'" : "";
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE (course_name LIKE '%$keyword%' 
                                       OR file_name LIKE '%$keyword%')
                                       $deptFilter
                                       ORDER BY display_order ASC, course_name ASC");
            } else {
                $deptFilter = isset($_GET['department']) ? "AND department_code = '" . $conn->real_escape_string($_GET['department']) . "'" : "";
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE (resource_title LIKE '%$keyword%' 
                                       OR category LIKE '%$keyword%')
                                       $deptFilter
                                       ORDER BY display_order ASC, resource_title ASC");
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

            if (!empty($_GET['department']) && ($table === 'course_files' || $table === 'resources')) {
                $department = $conn->real_escape_string($_GET['department']);
                $whereClauses[] = "department_code = '$department'";
            }

            if (!empty($_GET['category']) && $table === 'resources') {
                $category = $conn->real_escape_string($_GET['category']);
                $whereClauses[] = "category = '$category'";
            }

            $query = "SELECT * FROM `$tableName`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            if ($table === 'departments') {
                $query .= " ORDER BY display_order ASC, name ASC";
            } else if ($table === 'course_files') {
                $query .= " ORDER BY display_order ASC, course_name ASC";
            } else {
                $query .= " ORDER BY display_order ASC, resource_title ASC";
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

        if ($table === 'departments') {
            $requiredFields = ['code', 'name', 'full_name'];
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

            $code = $conn->real_escape_string($input['code']);
            $name = $conn->real_escape_string($input['name']);
            $full_name = $conn->real_escape_string($input['full_name']);
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            $checkDuplicate = $conn->query("SELECT id FROM `$tableName` WHERE code = '$code'");
            if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
                echo json_encode(["success" => false, "error" => "A department with this code already exists"]);
                break;
            }

            $sql = "INSERT INTO `$tableName` (code, name, full_name, is_active, display_order) 
                    VALUES ('$code', '$name', '$full_name', $is_active, $display_order)";

        } else if ($table === 'course_files') {
            $requiredFields = ['department_code', 'course_name', 'file_name', 'file_url'];
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

            $department_code = $conn->real_escape_string($input['department_code']);
            $course_name = $conn->real_escape_string($input['course_name']);
            $file_name = $conn->real_escape_string($input['file_name']);
            $file_url = $conn->real_escape_string($input['file_url']);
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            // Verify department exists
            $checkDepartment = $conn->query("SELECT id FROM nba_departments WHERE code = '$department_code'");
            if (!$checkDepartment || $checkDepartment->num_rows === 0) {
                echo json_encode(["success" => false, "error" => "Invalid department_code"]);
                break;
            }

            $sql = "INSERT INTO `$tableName` (department_code, course_name, file_name, file_url, is_active, display_order) 
                    VALUES ('$department_code', '$course_name', '$file_name', '$file_url', $is_active, $display_order)";

        } else { // resources
            $requiredFields = ['department_code', 'resource_title', 'resource_url'];
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

            $department_code = $conn->real_escape_string($input['department_code']);
            $resource_title = $conn->real_escape_string($input['resource_title']);
            $resource_url = $conn->real_escape_string($input['resource_url']);
            $category = !empty($input['category']) ? "'" . $conn->real_escape_string($input['category']) . "'" : "NULL";
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            $checkDepartment = $conn->query("SELECT id FROM nba_departments WHERE code = '$department_code'");
            if (!$checkDepartment || $checkDepartment->num_rows === 0) {
                echo json_encode(["success" => false, "error" => "Invalid department_code"]);
                break;
            }

            $sql = "INSERT INTO `$tableName` (department_code, resource_title, resource_url, category, is_active, display_order) 
                    VALUES ('$department_code', '$resource_title', '$resource_url', $category, $is_active, $display_order)";
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
            if ($table === 'departments') {
                $whereClauses[] = "(name LIKE '%$keyword%' OR full_name LIKE '%$keyword%' OR code LIKE '%$keyword%')";
            } else if ($table === 'course_files') {
                $whereClauses[] = "(course_name LIKE '%$keyword%' OR file_name LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(resource_title LIKE '%$keyword%' OR category LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        if ($table === 'departments') {
            $allowedFields = ['code', 'name', 'full_name', 'is_active', 'display_order'];
        } else if ($table === 'course_files') {
            $allowedFields = ['department_code', 'course_name', 'file_name', 'file_url', 'is_active', 'display_order'];
        } else {
            $allowedFields = ['department_code', 'resource_title', 'resource_url', 'category', 'is_active', 'display_order'];
        }
        
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
            if ($table === 'departments') {
                $whereClauses[] = "(name LIKE '%$keyword%' OR full_name LIKE '%$keyword%' OR code LIKE '%$keyword%')";
            } else if ($table === 'course_files') {
                $whereClauses[] = "(course_name LIKE '%$keyword%' OR file_name LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(resource_title LIKE '%$keyword%' OR category LIKE '%$keyword%')";
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