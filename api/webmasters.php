<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Faculty Incharges Table
    $createFacultyIncharge = "CREATE TABLE IF NOT EXISTS webmaster_faculty (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        role VARCHAR(255) NOT NULL,
        image_url VARCHAR(500),
        linkedin_url VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createFacultyIncharge)) {
        return ["success" => false, "error" => "Failed to create webmaster_faculty table: " . $conn->error];
    }

    // Student Leads Table
    $createStudentLeads = "CREATE TABLE IF NOT EXISTS webmaster_students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        role TEXT NOT NULL,
        batch VARCHAR(50) NOT NULL,
        image_url VARCHAR(500),
        linkedin_url VARCHAR(500),
        github_url VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createStudentLeads)) {
        return ["success" => false, "error" => "Failed to create webmaster_students table: " . $conn->error];
    }

    // Create Indexes
    $conn->query("CREATE INDEX IF NOT EXISTS idx_faculty_order ON webmaster_faculty(display_order, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_students_batch ON webmaster_students(batch, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_students_order ON webmaster_students(display_order, is_active)");

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
            echo json_encode(["success" => false, "error" => "Entity parameter required (faculty/students)"]);
            break;
        }

        $tableMap = [
            'faculty' => 'webmaster_faculty',
            'students' => 'webmaster_students'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];

        // Search by keyword
        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $searchFields = [];

            switch($entity) {
                case 'faculty':
                    $searchFields = ['name', 'role'];
                    break;
                case 'students':
                    $searchFields = ['name', 'role', 'batch'];
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

            if ($entity === 'students' && !empty($_GET['batch'])) {
                $batch = $conn->real_escape_string($_GET['batch']);
                $whereClauses[] = "batch = '$batch'";
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (faculty/students)"]);
            break;
        }

        $tableMap = [
            'faculty' => 'webmaster_faculty',
            'students' => 'webmaster_students'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];

        $requiredFields = [];
        switch($entity) {
            case 'faculty':
                $requiredFields = ['name', 'role'];
                break;
            case 'students':
                $requiredFields = ['name', 'role', 'batch'];
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

        if (!in_array('display_order', $columns)) {
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (faculty/students)"]);
            break;
        }

        $tableMap = [
            'faculty' => 'webmaster_faculty',
            'students' => 'webmaster_students'
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
        if (!empty($_GET['batch']) && $entity === 'students') {
            $batch = $conn->real_escape_string($_GET['batch']);
            $whereClauses[] = "batch = '$batch'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR role LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/batch/keyword required)"]);
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (faculty/students)"]);
            break;
        }

        $tableMap = [
            'faculty' => 'webmaster_faculty',
            'students' => 'webmaster_students'
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
        if (!empty($_GET['batch']) && $entity === 'students') {
            $batch = $conn->real_escape_string($_GET['batch']);
            $whereClauses[] = "batch = '$batch'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(name LIKE '%$keyword%' OR role LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/batch/keyword required)"]);
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