<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createAlumni = "CREATE TABLE IF NOT EXISTS `alumni` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        image VARCHAR(255),
        work_company VARCHAR(255),
        batch_year VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createAlumni)) {
        return ["success" => false, "error" => "Failed to create alumni table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_alumni_department ON `alumni`(department)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_alumni_name ON `alumni`(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_alumni_batch_year ON `alumni`(batch_year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_alumni_work_company ON `alumni`(work_company)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_alumni_dept_batch ON `alumni`(department, batch_year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_alumni_created ON `alumni`(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function alumniTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'alumni'");
    return $result && $result->num_rows > 0;
}

function isValidBatchYear($batch_year) {
    if (preg_match('/^(19|20)\d{2}$/', $batch_year)) {
        return true;
    }
    
    if (preg_match('/^(19|20)\d{2}-(19|20)\d{2}$/', $batch_year)) {
        return true;
    }
    
    if (preg_match('/^(Batch|Class of) (19|20)\d{2}$/i', $batch_year)) {
        return true;
    }
    
    if (preg_match('/^(Batch|Class of) (19|20)\d{2}-(19|20)\d{2}$/i', $batch_year)) {
        return true;
    }
    
    return false;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $result = $conn->query("SELECT * FROM `alumni` 
                                   WHERE department LIKE '%$keyword%' 
                                   OR name LIKE '%$keyword%' 
                                   OR work_company LIKE '%$keyword%'
                                   OR batch_year LIKE '%$keyword%'
                                   ORDER BY batch_year DESC, name ASC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No alumni found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['department'])) {
                $department = $conn->real_escape_string($_GET['department']);
                $whereClauses[] = "department = '$department'";
            }

            if (!empty($_GET['name'])) {
                $name = $conn->real_escape_string($_GET['name']);
                $whereClauses[] = "name LIKE '%$name%'";
            }

            if (!empty($_GET['work_company'])) {
                $work_company = $conn->real_escape_string($_GET['work_company']);
                $whereClauses[] = "work_company LIKE '%$work_company%'";
            }

            if (!empty($_GET['batch_year'])) {
                $batch_year = $conn->real_escape_string($_GET['batch_year']);
                $whereClauses[] = "batch_year = '$batch_year'";
            }

            $query = "SELECT * FROM `alumni`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY batch_year DESC, name ASC";

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

        $requiredFields = ['department', 'name', 'batch_year'];
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

        if (!isValidBatchYear($input['batch_year'])) {
            echo json_encode(["success" => false, "error" => "Invalid batch year format. Use: YYYY, YYYY-YYYY, Batch YYYY, Class of YYYY, or their range formats"]);
            break;
        }

        $department = $conn->real_escape_string($input['department']);
        $name = $conn->real_escape_string($input['name']);
        $image = isset($input['image']) ? $conn->real_escape_string($input['image']) : null;
        $work_company = isset($input['work_company']) ? $conn->real_escape_string($input['work_company']) : null;
        $batch_year = $conn->real_escape_string($input['batch_year']);

        $checkDuplicate = $conn->query("SELECT id FROM `alumni` WHERE department = '$department' AND name = '$name' AND batch_year = '$batch_year'");
        if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "Alumni with this name already exists in the same department and batch year"]);
            break;
        }

        $sql = "INSERT INTO `alumni` (department, name, image, work_company, batch_year) 
                VALUES ('$department', '$name', " . 
                ($image ? "'$image'" : "NULL") . ", " .
                ($work_company ? "'$work_company'" : "NULL") . ", '$batch_year')";

        if ($conn->query($sql)) {
            $alumni_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "alumni_id" => $alumni_id,
                "department" => $input['department'],
                "name" => $input['name'],
                "batch_year" => $input['batch_year']
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
        if (!empty($_GET['department'])) {
            $department = $conn->real_escape_string($_GET['department']);
            $whereClauses[] = "department = '$department'";
        }
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name LIKE '%$name%'";
        }
        if (!empty($_GET['work_company'])) {
            $work_company = $conn->real_escape_string($_GET['work_company']);
            $whereClauses[] = "work_company LIKE '%$work_company%'";
        }
        if (!empty($_GET['batch_year'])) {
            $batch_year = $conn->real_escape_string($_GET['batch_year']);
            $whereClauses[] = "batch_year = '$batch_year'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department LIKE '%$keyword%' OR name LIKE '%$keyword%' OR work_company LIKE '%$keyword%' OR batch_year LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/name/work_company/batch_year/keyword required)"]);
            break;
        }

        $allowedFields = ['department', 'name', 'image', 'work_company', 'batch_year'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'batch_year' && !empty($value) && !isValidBatchYear($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid batch year format. Use: YYYY, YYYY-YYYY, Batch YYYY, Class of YYYY, or their range formats"]);
                    break 2;
                }
                
                if ($value === null || $value === '') {
                    if (in_array($key, ['department', 'name', 'batch_year'])) {
                        echo json_encode(["success" => false, "error" => "$key field cannot be empty"]);
                        break 2;
                    }
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
        $sql = "UPDATE `alumni` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['department'])) {
            $department = $conn->real_escape_string($_GET['department']);
            $whereClauses[] = "department = '$department'";
        }
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name LIKE '%$name%'";
        }
        if (!empty($_GET['work_company'])) {
            $work_company = $conn->real_escape_string($_GET['work_company']);
            $whereClauses[] = "work_company LIKE '%$work_company%'";
        }
        if (!empty($_GET['batch_year'])) {
            $batch_year = $conn->real_escape_string($_GET['batch_year']);
            $whereClauses[] = "batch_year = '$batch_year'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department LIKE '%$keyword%' OR name LIKE '%$keyword%' OR work_company LIKE '%$keyword%' OR batch_year LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/name/work_company/batch_year/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM `alumni` WHERE " . implode(" AND ", $whereClauses);

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "deleted_rows" => $conn->affected_rows]);
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