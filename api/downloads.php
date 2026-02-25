<?php
include '../server.php';

header("Content-Type: application/json");

function createTableIfNotExist($conn) {
    $createDownloads = "CREATE TABLE IF NOT EXISTS `downloads` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(500) NOT NULL,
        link VARCHAR(500) NOT NULL,
        sort_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createDownloads)) {
        return ["success" => false, "error" => "Failed to create downloads table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_downloads_active ON `downloads`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_downloads_sort ON `downloads`(sort_order)");

    return ["success" => true, "message" => "Table created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $initResult = createTableIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $whereClauses = ["is_active = TRUE"];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }

        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        $query = "SELECT * FROM `downloads`";
        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $query .= " ORDER BY `sort_order` ASC, `id` ASC";

        $result = $conn->query($query);
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(["success" => true, "data" => $data]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'POST':
        $initResult = createTableIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $requiredFields = ['title', 'link'];
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

        $title      = $conn->real_escape_string($input['title']);
        $link       = $conn->real_escape_string($input['link']);
        $sort_order = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
        $is_active  = isset($input['is_active'])  ? (int)$input['is_active']  : 1;

        $sql = "INSERT INTO `downloads` (`title`, `link`, `sort_order`, `is_active`)
                VALUES ('$title', '$link', $sort_order, $is_active)";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "id" => $conn->insert_id, "title" => $title]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'PATCH':
        $initResult = createTableIfNotExist($conn);
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
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        $allowedFields = ['title', 'link', 'sort_order', 'is_active'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'sort_order' || $key === 'is_active') {
                    $updates[] = "`$key` = " . (int)$value;
                    continue;
                }
                if (is_null($value) || $value === '') {
                    $updates[] = "`$key` = NULL";
                } else {
                    $value = $conn->real_escape_string($value);
                    $updates[] = "`$key` = '$value'";
                }
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE `downloads` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "updated_rows" => $conn->affected_rows]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $initResult = createTableIfNotExist($conn);
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
            $whereClauses[] = "title LIKE '%$keyword%'";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        // Soft delete
        $sql = "UPDATE `downloads` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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