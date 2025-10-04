<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createCouncil = "CREATE TABLE IF NOT EXISTS `student_council` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        position VARCHAR(100) NOT NULL,
        name VARCHAR(255) NOT NULL,
        roll_no VARCHAR(50) NOT NULL,
        year_semester VARCHAR(50) NOT NULL,
        branch VARCHAR(50) NOT NULL,
        mobile_no VARCHAR(15) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createCouncil)) {
        return ["success" => false, "error" => "Failed to create student_council table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_council_position ON `student_council`(position)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_council_name ON `student_council`(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_council_roll_no ON `student_council`(roll_no)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_council_branch ON `student_council`(branch)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_council_year_sem ON `student_council`(year_semester)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_council_is_active ON `student_council`(is_active)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidMobileNo($mobile_no) {
    return preg_match('/^[6-9]\d{9}$/', $mobile_no);
}

function isValidRollNo($roll_no) {
    return preg_match('/^[A-Z]{2}\d{5}$/', $roll_no);
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
            $result = $conn->query("SELECT * FROM `student_council` 
                                   WHERE position LIKE '%$keyword%' 
                                   OR name LIKE '%$keyword%' 
                                   OR roll_no LIKE '%$keyword%'
                                   OR branch LIKE '%$keyword%'
                                   OR year_semester LIKE '%$keyword%'
                                   ORDER BY 
                                   CASE position
                                       WHEN 'President' THEN 1
                                       WHEN 'Vice President' THEN 2
                                       WHEN 'Secretary' THEN 3
                                       WHEN 'Joint Secretary' THEN 4
                                       WHEN 'Treasurer' THEN 5
                                       ELSE 6
                                   END, name ASC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No student council members found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['position'])) {
                $position = $conn->real_escape_string($_GET['position']);
                $whereClauses[] = "position = '$position'";
            }

            if (!empty($_GET['name'])) {
                $name = $conn->real_escape_string($_GET['name']);
                $whereClauses[] = "name LIKE '%$name%'";
            }

            if (!empty($_GET['roll_no'])) {
                $roll_no = $conn->real_escape_string($_GET['roll_no']);
                $whereClauses[] = "roll_no = '$roll_no'";
            }

            if (!empty($_GET['branch'])) {
                $branch = $conn->real_escape_string($_GET['branch']);
                $whereClauses[] = "branch = '$branch'";
            }

            if (!empty($_GET['year_semester'])) {
                $year_semester = $conn->real_escape_string($_GET['year_semester']);
                $whereClauses[] = "year_semester = '$year_semester'";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM `student_council`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY 
                       CASE position
                           WHEN 'President' THEN 1
                           WHEN 'Vice President' THEN 2
                           WHEN 'Secretary' THEN 3
                           WHEN 'Joint Secretary' THEN 4
                           WHEN 'Treasurer' THEN 5
                           ELSE 6
                       END, name ASC";

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

        $requiredFields = ['position', 'name', 'roll_no', 'year_semester', 'branch', 'mobile_no'];
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

        if (!isValidMobileNo($input['mobile_no'])) {
            echo json_encode(["success" => false, "error" => "Invalid mobile number format. Must be 10 digits starting with 6-9"]);
            break;
        }

        if (!isValidRollNo($input['roll_no'])) {
            echo json_encode(["success" => false, "error" => "Invalid roll number format. Must be 2 letters followed by 5 digits (e.g., CO22306)"]);
            break;
        }

        $position = $conn->real_escape_string($input['position']);
        $name = $conn->real_escape_string($input['name']);
        $roll_no = $conn->real_escape_string($input['roll_no']);
        $year_semester = $conn->real_escape_string($input['year_semester']);
        $branch = $conn->real_escape_string($input['branch']);
        $mobile_no = $conn->real_escape_string($input['mobile_no']);
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        $checkDuplicatePosition = $conn->query("SELECT id FROM `student_council` WHERE position = '$position' AND is_active = TRUE");
        if ($checkDuplicatePosition && $checkDuplicatePosition->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "This position is already filled. Please update or delete the existing record first."]);
            break;
        }

        $checkDuplicateRoll = $conn->query("SELECT id FROM `student_council` WHERE roll_no = '$roll_no' AND is_active = TRUE");
        if ($checkDuplicateRoll && $checkDuplicateRoll->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "A student with this roll number is already in the council"]);
            break;
        }

        $sql = "INSERT INTO `student_council` (position, name, roll_no, year_semester, branch, mobile_no, is_active) 
                VALUES ('$position', '$name', '$roll_no', '$year_semester', '$branch', '$mobile_no', $is_active)";

        if ($conn->query($sql)) {
            $council_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "council_id" => $council_id,
                "position" => $input['position'],
                "name" => $input['name'],
                "roll_no" => $input['roll_no']
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
        if (!empty($_GET['position'])) {
            $position = $conn->real_escape_string($_GET['position']);
            $whereClauses[] = "position = '$position'";
        }
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name LIKE '%$name%'";
        }
        if (!empty($_GET['roll_no'])) {
            $roll_no = $conn->real_escape_string($_GET['roll_no']);
            $whereClauses[] = "roll_no = '$roll_no'";
        }
        if (!empty($_GET['branch'])) {
            $branch = $conn->real_escape_string($_GET['branch']);
            $whereClauses[] = "branch = '$branch'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(position LIKE '%$keyword%' OR name LIKE '%$keyword%' OR roll_no LIKE '%$keyword%' OR branch LIKE '%$keyword%' OR year_semester LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/position/name/roll_no/branch/keyword required)"]);
            break;
        }

        $allowedFields = ['position', 'name', 'roll_no', 'year_semester', 'branch', 'mobile_no', 'is_active'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_active') {
                    $updates[] = "$key = " . (int)$value;
                    continue;
                }

                if (empty($value)) {
                    echo json_encode(["success" => false, "error" => "$key field cannot be empty"]);
                    break 2;
                }

                if ($key === 'mobile_no' && !isValidMobileNo($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid mobile number format. Must be 10 digits starting with 6-9"]);
                    break 2;
                }

                if ($key === 'roll_no' && !isValidRollNo($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid roll number format. Must be 2 letters followed by 5 digits (e.g., CO22306)"]);
                    break 2;
                }

                $value = $conn->real_escape_string($value);
                $updates[] = "$key = '$value'";
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE `student_council` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['position'])) {
            $position = $conn->real_escape_string($_GET['position']);
            $whereClauses[] = "position = '$position'";
        }
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name LIKE '%$name%'";
        }
        if (!empty($_GET['roll_no'])) {
            $roll_no = $conn->real_escape_string($_GET['roll_no']);
            $whereClauses[] = "roll_no = '$roll_no'";
        }
        if (!empty($_GET['branch'])) {
            $branch = $conn->real_escape_string($_GET['branch']);
            $whereClauses[] = "branch = '$branch'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(position LIKE '%$keyword%' OR name LIKE '%$keyword%' OR roll_no LIKE '%$keyword%' OR branch LIKE '%$keyword%' OR year_semester LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/position/name/roll_no/branch/keyword required)"]);
            break;
        }

        $sql = "UPDATE `student_council` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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