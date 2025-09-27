<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createAcademicHeads = "CREATE TABLE IF NOT EXISTS `academicheads` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        branch VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        img VARCHAR(255),
        designation VARCHAR(255) NOT NULL,
        edu TEXT,
        interest TEXT,
        number VARCHAR(20),
        email VARCHAR(255),
        address TEXT,
        resume VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createAcademicHeads)) {
        return ["success" => false, "error" => "Failed to create academicheads table: " . $conn->error];
    }

    $checkColumn = $conn->query("SHOW COLUMNS FROM `academicheads` LIKE 'resume'");
    if ($checkColumn->num_rows == 0) {
        $addResumeColumn = "ALTER TABLE `academicheads` ADD COLUMN `resume` VARCHAR(255) NULL";
        if (!$conn->query($addResumeColumn)) {
            return ["success" => false, "error" => "Failed to add resume column: " . $conn->error];
        }
    }

    $checkCreatedAt = $conn->query("SHOW COLUMNS FROM `academicheads` LIKE 'created_at'");
    if ($checkCreatedAt->num_rows == 0) {
        $addCreatedAt = "ALTER TABLE `academicheads` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        if (!$conn->query($addCreatedAt)) {
            return ["success" => false, "error" => "Failed to add created_at column: " . $conn->error];
        }
    }

    $checkUpdatedAt = $conn->query("SHOW COLUMNS FROM `academicheads` LIKE 'updated_at'");
    if ($checkUpdatedAt->num_rows == 0) {
        $addUpdatedAt = "ALTER TABLE `academicheads` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        if (!$conn->query($addUpdatedAt)) {
            return ["success" => false, "error" => "Failed to add updated_at column: " . $conn->error];
        }
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_academicheads_branch ON `academicheads`(branch)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_academicheads_name ON `academicheads`(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_academicheads_designation ON `academicheads`(designation)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_academicheads_email ON `academicheads`(email)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_academicheads_created ON `academicheads`(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function isValidPhone($phone) {
    return preg_match('/^[\d\s\-\(\)\+]+$/', $phone) && strlen(preg_replace('/[^\d]/', '', $phone)) >= 10;
}

function isValidPDF($file_path) {
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return $file_extension === 'pdf';
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
            $result = $conn->query("SELECT * FROM `academicheads`
                                   WHERE branch LIKE '%$keyword%'
                                   OR name LIKE '%$keyword%'
                                   OR designation LIKE '%$keyword%'
                                   OR email LIKE '%$keyword%'
                                   OR interest LIKE '%$keyword%'
                                   ORDER BY id DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No academic heads found with that keyword"]);
            }
        } elseif (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $conn->query("SELECT * FROM `academicheads` WHERE id = $id");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_assoc();
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "Academic head not found"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['branch'])) {
                $branch = $conn->real_escape_string($_GET['branch']);
                $whereClauses[] = "branch = '$branch'";
            }

            if (!empty($_GET['designation'])) {
                $designation = $conn->real_escape_string($_GET['designation']);
                $whereClauses[] = "designation = '$designation'";
            }

            if (!empty($_GET['name'])) {
                $name = $conn->real_escape_string($_GET['name']);
                $whereClauses[] = "name LIKE '%$name%'";
            }

            $query = "SELECT * FROM `academicheads`";

            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY id DESC";

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

        $requiredFields = ['branch', 'name', 'designation'];
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

        if (!empty($input['email']) && !isValidEmail($input['email'])) {
            echo json_encode(["success" => false, "error" => "Invalid email format"]);
            break;
        }

        if (!empty($input['number']) && !isValidPhone($input['number'])) {
            echo json_encode(["success" => false, "error" => "Invalid phone number format"]);
            break;
        }

        if (!empty($input['resume']) && !isValidPDF($input['resume'])) {
            echo json_encode(["success" => false, "error" => "Resume must be a PDF file"]);
            break;
        }

        $branch = $conn->real_escape_string($input['branch']);
        $name = $conn->real_escape_string($input['name']);
        $img = isset($input['img']) ? $conn->real_escape_string($input['img']) : null;
        $designation = $conn->real_escape_string($input['designation']);
        $edu = isset($input['edu']) ? $conn->real_escape_string($input['edu']) : null;
        $interest = isset($input['interest']) ? $conn->real_escape_string($input['interest']) : null;
        $number = isset($input['number']) ? $conn->real_escape_string($input['number']) : null;
        $email = isset($input['email']) ? $conn->real_escape_string($input['email']) : null;
        $address = isset($input['address']) ? $conn->real_escape_string($input['address']) : null;
        $resume = isset($input['resume']) ? $conn->real_escape_string($input['resume']) : null;

        $sql = "INSERT INTO `academicheads`
                (branch, name, img, designation, edu, interest, number, email, address, resume)
                VALUES ('$branch', '$name', " .
                ($img ? "'$img'" : "NULL") . ", '$designation', " .
                ($edu ? "'$edu'" : "NULL") . ", " .
                ($interest ? "'$interest'" : "NULL") . ", " .
                ($number ? "'$number'" : "NULL") . ", " .
                ($email ? "'$email'" : "NULL") . ", " .
                ($address ? "'$address'" : "NULL") . ", " .
                ($resume ? "'$resume'" : "NULL") . ")";

        if ($conn->query($sql)) {
            echo json_encode([
                "success" => true,
                "id" => $conn->insert_id,
                "name" => $input['name'],
                "branch" => $input['branch'],
                "designation" => $input['designation']
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
        if (!empty($_GET['branch'])) {
            $branch = $conn->real_escape_string($_GET['branch']);
            $whereClauses[] = "branch = '$branch'";
        }
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name LIKE '%$name%'";
        }
        if (!empty($_GET['designation'])) {
            $designation = $conn->real_escape_string($_GET['designation']);
            $whereClauses[] = "designation = '$designation'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(branch LIKE '%$keyword%' OR name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR email LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/branch/name/designation/email/keyword required)"]);
            break;
        }

        $allowedFields = ['branch', 'name', 'img', 'designation', 'edu', 'interest', 'number', 'email', 'address', 'resume'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
                }

                if ($key === 'number' && !empty($value) && !isValidPhone($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid phone number format"]);
                    break 2;
                }

                if ($key === 'resume' && !empty($value) && !isValidPDF($value)) {
                    echo json_encode(["success" => false, "error" => "Resume must be a PDF file"]);
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

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE `academicheads` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['branch'])) {
            $branch = $conn->real_escape_string($_GET['branch']);
            $whereClauses[] = "branch = '$branch'";
        }
        if (!empty($_GET['name'])) {
            $name = $conn->real_escape_string($_GET['name']);
            $whereClauses[] = "name LIKE '%$name%'";
        }
        if (!empty($_GET['designation'])) {
            $designation = $conn->real_escape_string($_GET['designation']);
            $whereClauses[] = "designation = '$designation'";
        }
        if (!empty($_GET['email'])) {
            $email = $conn->real_escape_string($_GET['email']);
            $whereClauses[] = "email = '$email'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(branch LIKE '%$keyword%' OR name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR email LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/branch/name/designation/email/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM `academicheads` WHERE " . implode(" AND ", $whereClauses);

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