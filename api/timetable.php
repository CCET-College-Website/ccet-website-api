<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createTimeTable = "CREATE TABLE IF NOT EXISTS `timetable` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department VARCHAR(255) NOT NULL,
        semester VARCHAR(100) NOT NULL,
        pdf VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createTimeTable)) {
        return ["success" => false, "error" => "Failed to create timetable table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_timetable_department ON `timetable`(department)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_timetable_semester ON `timetable`(semester)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_timetable_dept_sem ON `timetable`(department, semester)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_timetable_created ON `timetable`(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function timetableTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'timetable'");
    return $result && $result->num_rows > 0;
}

function isValidSemester($semester) {
    $validSemesters = ['1', '2', '3', '4', '5', '6', '7', '8', 
                       'First Semester', 'Second Semester', 'Third Semester', 'Fourth Semester',
                       'Fifth Semester', 'Sixth Semester', 'Seventh Semester', 'Eighth Semester',
                       '1st Semester', '2nd Semester', '3rd Semester', '4th Semester',
                       '5th Semester', '6th Semester', '7th Semester', '8th Semester',
                       'Semester 1', 'Semester 2', 'Semester 3', 'Semester 4',
                       'Semester 5', 'Semester 6', 'Semester 7', 'Semester 8'];
    
    if (in_array($semester, $validSemesters)) {
        return true;
    }
    
    if (preg_match('/^(First|Second|Third|Fourth|Fifth|Sixth|Seventh|Eighth|1st|2nd|3rd|4th|5th|6th|7th|8th|Semester \d|\d) Semester \[(\d{4})-(\d{4})\]$/i', $semester, $matches)) {
        return true;
    }
    
    if (preg_match('/^[1-8] \[(\d{4})-(\d{4})\]$/', $semester, $matches)) {
        return true;
    }
    
    if (preg_match('/^(\d{4})-(\d{4}) (Odd|Even)$/i', $semester, $matches)) {
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
            $result = $conn->query("SELECT * FROM `timetable` 
                                   WHERE department LIKE '%$keyword%' 
                                   OR semester LIKE '%$keyword%' 
                                   OR pdf LIKE '%$keyword%'
                                   ORDER BY department, semester, id DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No timetable found with that keyword"]);
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

            if (!empty($_GET['semester'])) {
                $semester = $conn->real_escape_string($_GET['semester']);
                $whereClauses[] = "semester = '$semester'";
            }

            $query = "SELECT * FROM `timetable`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY department, semester, id DESC";

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

        $requiredFields = ['department', 'semester', 'pdf'];
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

        if (!isValidSemester($input['semester'])) {
            echo json_encode(["success" => false, "error" => "Invalid semester format. Use: 1-8, First Semester-Eighth Semester, 1st Semester-8th Semester, Semester 1-8, with batch [YYYY-YYYY], or academic year format YYYY-YYYY Odd/Even"]);
            break;
        }

        $pdf_path = $input['pdf'];
        $file_extension = strtolower(pathinfo($pdf_path, PATHINFO_EXTENSION));
        if ($file_extension !== 'pdf') {
            echo json_encode(["success" => false, "error" => "Invalid file type. Only PDF files are allowed"]);
            break;
        }

        $department = $conn->real_escape_string($input['department']);
        $semester = $conn->real_escape_string($input['semester']);
        $pdf = $conn->real_escape_string($input['pdf']);

        $checkDuplicate = $conn->query("SELECT id FROM `timetable` WHERE department = '$department' AND semester = '$semester'");
        if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "Timetable for this department and semester already exists"]);
            break;
        }

        $sql = "INSERT INTO `timetable` (department, semester, pdf) 
                VALUES ('$department', '$semester', '$pdf')";

        if ($conn->query($sql)) {
            $timetable_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "timetable_id" => $timetable_id,
                "department" => $input['department'],
                "semester" => $input['semester'],
                "pdf" => $input['pdf']
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
        if (!empty($_GET['semester'])) {
            $semester = $conn->real_escape_string($_GET['semester']);
            $whereClauses[] = "semester = '$semester'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department LIKE '%$keyword%' OR semester LIKE '%$keyword%' OR pdf LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/semester/keyword required)"]);
            break;
        }

        $allowedFields = ['department', 'semester', 'pdf'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'semester' && !empty($value) && !isValidSemester($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid semester format. Use: 1-8, First Semester-Eighth Semester, 1st Semester-8th Semester, Semester 1-8, with batch [YYYY-YYYY], or academic year format YYYY-YYYY Odd/Even"]);
                    break 2;
                }
                
                if ($key === 'pdf' && !empty($value)) {
                    $file_extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                    if ($file_extension !== 'pdf') {
                        echo json_encode(["success" => false, "error" => "Invalid file type. Only PDF files are allowed"]);
                        break 2;
                    }
                }
                
                if ($value === null || $value === '') {
                    if ($key === 'pdf') {
                        echo json_encode(["success" => false, "error" => "PDF field cannot be empty"]);
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
        $sql = "UPDATE `timetable` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['semester'])) {
            $semester = $conn->real_escape_string($_GET['semester']);
            $whereClauses[] = "semester = '$semester'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department LIKE '%$keyword%' OR semester LIKE '%$keyword%' OR pdf LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/semester/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM `timetable` WHERE " . implode(" AND ", $whereClauses);

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