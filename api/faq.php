<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createFaqCategory = "CREATE TABLE IF NOT EXISTS `faq_category` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createFaq = "CREATE TABLE IF NOT EXISTS `faq` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES faq_category(id) ON DELETE CASCADE
    )";
    
    $createUserQuestions = "CREATE TABLE IF NOT EXISTS `user_questions` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question TEXT NOT NULL,
        user_email VARCHAR(255),
        user_name VARCHAR(255),
        status ENUM('pending', 'answered', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createFaqCategory)) {
        return ["success" => false, "error" => "Failed to create faq_category table: " . $conn->error];
    }

    if (!$conn->query($createFaq)) {
        return ["success" => false, "error" => "Failed to create faq table: " . $conn->error];
    }

    if (!$conn->query($createUserQuestions)) {
        return ["success" => false, "error" => "Failed to create user_questions table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_category_name ON `faq_category`(name)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_category_is_active ON `faq_category`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_category_order ON `faq_category`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_faq_category ON `faq`(category_id)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_faq_is_active ON `faq`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_faq_order ON `faq`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_user_questions_status ON `user_questions`(status)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Determine which table to operate on
$table = isset($_GET['table']) ? $_GET['table'] : 'faq';

if (!in_array($table, ['faq', 'category', 'user_questions'])) {
    echo json_encode(["success" => false, "error" => "Invalid table. Use 'faq', 'category', or 'user_questions'"]);
    exit;
}

$tableName = $table === 'category' ? 'faq_category' : ($table === 'user_questions' ? 'user_questions' : 'faq');

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        // Special endpoint to get FAQs grouped by category
        if (isset($_GET['grouped']) && $_GET['grouped'] === 'true') {
            $query = "SELECT 
                        c.id as category_id,
                        c.name as category_name,
                        c.display_order as category_order,
                        f.id as faq_id,
                        f.question,
                        f.answer,
                        f.display_order as faq_order
                      FROM faq_category c
                      LEFT JOIN faq f ON c.id = f.category_id AND f.is_active = TRUE
                      WHERE c.is_active = TRUE
                      ORDER BY c.display_order ASC, c.name ASC, f.display_order ASC, f.id ASC";
            
            $result = $conn->query($query);
            if ($result) {
                $groupedData = [];
                while ($row = $result->fetch_assoc()) {
                    $categoryId = $row['category_id'];
                    if (!isset($groupedData[$categoryId])) {
                        $groupedData[$categoryId] = [
                            'category_id' => $row['category_id'],
                            'category_name' => $row['category_name'],
                            'category_order' => $row['category_order'],
                            'faqs' => []
                        ];
                    }
                    if ($row['faq_id']) {
                        $groupedData[$categoryId]['faqs'][] = [
                            'id' => $row['faq_id'],
                            'question' => $row['question'],
                            'answer' => $row['answer'],
                            'display_order' => $row['faq_order']
                        ];
                    }
                }
                echo json_encode(array_values($groupedData));
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            
            if ($table === 'faq') {
                $result = $conn->query("SELECT f.*, c.name as category_name 
                                       FROM `$tableName` f
                                       LEFT JOIN faq_category c ON f.category_id = c.id
                                       WHERE f.question LIKE '%$keyword%' 
                                       OR f.answer LIKE '%$keyword%'
                                       OR c.name LIKE '%$keyword%'
                                       ORDER BY f.display_order ASC, f.id ASC");
            } else if ($table === 'category') {
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE name LIKE '%$keyword%'
                                       ORDER BY display_order ASC, name ASC");
            } else {
                $result = $conn->query("SELECT * FROM `$tableName` 
                                       WHERE question LIKE '%$keyword%'
                                       ORDER BY created_at DESC");
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

            if (!empty($_GET['category_id']) && $table === 'faq') {
                $category_id = (int)$_GET['category_id'];
                $whereClauses[] = "category_id = $category_id";
            }

            if (!empty($_GET['status']) && $table === 'user_questions') {
                $status = $conn->real_escape_string($_GET['status']);
                $whereClauses[] = "status = '$status'";
            }

            if (isset($_GET['is_active']) && $table !== 'user_questions') {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT ";
            
            if ($table === 'faq') {
                $query .= "f.*, c.name as category_name FROM `$tableName` f LEFT JOIN faq_category c ON f.category_id = c.id";
            } else {
                $query .= "* FROM `$tableName`";
            }
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            if ($table === 'category') {
                $query .= " ORDER BY display_order ASC, name ASC";
            } else if ($table === 'faq') {
                $query .= " ORDER BY f.display_order ASC, f.id ASC";
            } else {
                $query .= " ORDER BY created_at DESC";
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

        if ($table === 'category') {
            if (empty($input['name'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: name"]);
                break;
            }

            $name = $conn->real_escape_string($input['name']);
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            $checkDuplicate = $conn->query("SELECT id FROM `$tableName` WHERE name = '$name'");
            if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
                echo json_encode(["success" => false, "error" => "A category with this name already exists"]);
                break;
            }

            $sql = "INSERT INTO `$tableName` (name, display_order, is_active) 
                    VALUES ('$name', $display_order, $is_active)";

            if ($conn->query($sql)) {
                $record_id = $conn->insert_id;
                echo json_encode([
                    "success" => true, 
                    "id" => $record_id,
                    "name" => $input['name'],
                    "message" => "Category added successfully"
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } else if ($table === 'faq') {
            $requiredFields = ['category_id', 'question', 'answer'];
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

            $category_id = (int)$input['category_id'];
            $question = $conn->real_escape_string($input['question']);
            $answer = $conn->real_escape_string($input['answer']);
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;

            // Verify category exists
            $checkCategory = $conn->query("SELECT id FROM faq_category WHERE id = $category_id");
            if (!$checkCategory || $checkCategory->num_rows === 0) {
                echo json_encode(["success" => false, "error" => "Invalid category_id"]);
                break;
            }

            $sql = "INSERT INTO `$tableName` (category_id, question, answer, is_active, display_order) 
                    VALUES ($category_id, '$question', '$answer', $is_active, $display_order)";

            if ($conn->query($sql)) {
                $record_id = $conn->insert_id;
                echo json_encode([
                    "success" => true, 
                    "id" => $record_id,
                    "message" => "FAQ added successfully"
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } else { // user_questions
            if (empty($input['question'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: question"]);
                break;
            }

            $question = $conn->real_escape_string($input['question']);
            $user_email = !empty($input['user_email']) ? "'" . $conn->real_escape_string($input['user_email']) . "'" : "NULL";
            $user_name = !empty($input['user_name']) ? "'" . $conn->real_escape_string($input['user_name']) . "'" : "NULL";
            
            if (!empty($input['user_email']) && !isValidEmail($input['user_email'])) {
                echo json_encode(["success" => false, "error" => "Invalid email format"]);
                break;
            }

            $sql = "INSERT INTO `$tableName` (question, user_email, user_name) 
                    VALUES ('$question', $user_email, $user_name)";

            if ($conn->query($sql)) {
                $record_id = $conn->insert_id;
                echo json_encode([
                    "success" => true, 
                    "id" => $record_id,
                    "message" => "Question submitted successfully"
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
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($table === 'faq') {
                $whereClauses[] = "(question LIKE '%$keyword%' OR answer LIKE '%$keyword%')";
            } else if ($table === 'category') {
                $whereClauses[] = "(name LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(question LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        if ($table === 'category') {
            $allowedFields = ['name', 'display_order', 'is_active'];
        } else if ($table === 'faq') {
            $allowedFields = ['category_id', 'question', 'answer', 'is_active', 'display_order'];
        } else {
            $allowedFields = ['question', 'user_email', 'user_name', 'status'];
        }
        
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_active' || $key === 'display_order' || $key === 'category_id') {
                    $updates[] = "$key = " . (int)$value;
                    continue;
                }

                if ($key === 'user_email' && !empty($value) && !isValidEmail($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid email format"]);
                    break 2;
                }

                if ($key === 'status' && !in_array($value, ['pending', 'answered', 'rejected'])) {
                    echo json_encode(["success" => false, "error" => "Invalid status value"]);
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
            if ($table === 'faq') {
                $whereClauses[] = "(question LIKE '%$keyword%' OR answer LIKE '%$keyword%')";
            } else if ($table === 'category') {
                $whereClauses[] = "(name LIKE '%$keyword%')";
            } else {
                $whereClauses[] = "(question LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        if ($table === 'user_questions') {
            // Hard delete for user questions
            $sql = "DELETE FROM `$tableName` WHERE " . implode(" AND ", $whereClauses);
        } else {
            // Soft delete for FAQ and categories
            $sql = "UPDATE `$tableName` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);
        }

        if ($conn->query($sql)) {
            if ($table === 'user_questions') {
                echo json_encode(["success" => true, "deleted_rows" => $conn->affected_rows]);
            } else {
                echo json_encode(["success" => true, "soft_deleted_rows" => $conn->affected_rows]);
            }
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