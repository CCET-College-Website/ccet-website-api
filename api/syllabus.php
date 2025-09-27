<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createSyllabus = "CREATE TABLE IF NOT EXISTS `syllabus` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department VARCHAR(255) NOT NULL,
        year VARCHAR(100) NOT NULL,
        pdf VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createSyllabus)) {
        return ["success" => false, "error" => "Failed to create syllabus table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_syllabus_department ON `syllabus`(department)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_syllabus_year ON `syllabus`(year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_syllabus_dept_year ON `syllabus`(department, year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_syllabus_created ON `syllabus`(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function syllabusTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'syllabus'");
    return $result && $result->num_rows > 0;
}

function isValidYear($year) {
    $validYears = ['1', '2', '3', '4', 'First Year', 'Second Year', 'Third Year', 'Fourth Year', 
                   '1st Year', '2nd Year', '3rd Year', '4th Year'];
    
    if (in_array($year, $validYears) || preg_match('/^(19|20)\d{2}$/', $year)) {
        return true;
    }
    
    if (preg_match('/^(First|Second|Third|Fourth|1st|2nd|3rd|4th|\d) Year \[(\d{4})-(\d{4})\]$/i', $year, $matches)) {
        return true;
    }
    
    if (preg_match('/^[1-4] \[(\d{4})-(\d{4})\]$/', $year, $matches)) {
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
            $result = $conn->query("SELECT * FROM `syllabus` 
                                   WHERE department LIKE '%$keyword%' 
                                   OR year LIKE '%$keyword%' 
                                   OR pdf LIKE '%$keyword%'
                                   ORDER BY department, year, id DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No syllabus found with that keyword"]);
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

            if (!empty($_GET['year'])) {
                $year = $conn->real_escape_string($_GET['year']);
                $whereClauses[] = "year = '$year'";
            }

            $query = "SELECT * FROM `syllabus`";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY department, year, id DESC";

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

        $requiredFields = ['department', 'year', 'pdf'];
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

        if (!isValidYear($input['year'])) {
            echo json_encode(["success" => false, "error" => "Invalid year format. Use: 1-4, First Year-Fourth Year, 1st Year-4th Year, YYYY, or with batch [YYYY-YYYY]"]);
            break;
        }

        $pdf_path = $input['pdf'];
        $file_extension = strtolower(pathinfo($pdf_path, PATHINFO_EXTENSION));
        if ($file_extension !== 'pdf') {
            echo json_encode(["success" => false, "error" => "Invalid file type. Only PDF files are allowed"]);
            break;
        }

        $department = $conn->real_escape_string($input['department']);
        $year = $conn->real_escape_string($input['year']);
        $pdf = $conn->real_escape_string($input['pdf']);

        $checkDuplicate = $conn->query("SELECT id FROM `syllabus` WHERE department = '$department' AND year = '$year'");
        if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "Syllabus for this department and year already exists"]);
            break;
        }

        $sql = "INSERT INTO `syllabus` (department, year, pdf) 
                VALUES ('$department', '$year', '$pdf')";

        if ($conn->query($sql)) {
            $syllabus_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "syllabus_id" => $syllabus_id,
                "department" => $input['department'],
                "year" => $input['year'],
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
        if (!empty($_GET['year'])) {
            $year = $conn->real_escape_string($_GET['year']);
            $whereClauses[] = "year = '$year'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department LIKE '%$keyword%' OR year LIKE '%$keyword%' OR pdf LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/year/keyword required)"]);
            break;
        }

        $allowedFields = ['department', 'year', 'pdf'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'year' && !empty($value) && !isValidYear($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid year format. Use: 1-4, First Year-Fourth Year, 1st Year-4th Year, YYYY, or with batch [YYYY-YYYY]"]);
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
        $sql = "UPDATE `syllabus` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['year'])) {
            $year = $conn->real_escape_string($_GET['year']);
            $whereClauses[] = "year = '$year'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department LIKE '%$keyword%' OR year LIKE '%$keyword%' OR pdf LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department/year/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM `syllabus` WHERE " . implode(" AND ", $whereClauses);

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