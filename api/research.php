<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Research Faculty Table
    $createResearchFaculty = "CREATE TABLE IF NOT EXISTS research_faculty (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_image VARCHAR(500),
        name VARCHAR(255) NOT NULL,
        designation VARCHAR(255) NOT NULL,
        google_scholar_url VARCHAR(500),
        researchgate_url VARCHAR(500),
        orcid_url VARCHAR(500),
        other_links TEXT,
        department VARCHAR(100),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createResearchFaculty)) {
        return ["success" => false, "error" => "Failed to create research_faculty table: " . $conn->error];
    }

    // Create Indexes
    $conn->query("CREATE INDEX IF NOT EXISTS idx_research_dept ON research_faculty(department, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_research_order ON research_faculty(display_order, is_active)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
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

        // Search by keyword
        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $searchFields = ['name', 'designation', 'department'];

            $searchConditions = array_map(function($field) use ($keyword) {
                return "$field LIKE '%$keyword%'";
            }, $searchFields);

            $result = $conn->query("SELECT * FROM research_faculty WHERE (" . implode(" OR ", $searchConditions) . ") ORDER BY display_order ASC, id ASC");

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

            if (!empty($_GET['department'])) {
                $department = $conn->real_escape_string($_GET['department']);
                $whereClauses[] = "department = '$department'";
            }

            $query = "SELECT * FROM research_faculty";

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

        $requiredFields = ['name', 'designation'];
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

        $sql = "INSERT INTO research_faculty (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";

        if ($conn->query($sql)) {
            $id = $conn->insert_id;
            echo json_encode([
                "success" => true,
                "id" => $id,
                "message" => "Research faculty added successfully"
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
            $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR department LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
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
        $sql = "UPDATE research_faculty SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
            $whereClauses[] = "(name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR department LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        $sql = "UPDATE research_faculty SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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