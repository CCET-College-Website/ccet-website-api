<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Sports Gallery/Carousel Images Table
    $createGallery = "CREATE TABLE IF NOT EXISTS sports_gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_url VARCHAR(500) NOT NULL,
        image_alt TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createGallery)) {
        return ["success" => false, "error" => "Failed to create sports_gallery table: " . $conn->error];
    }

    // Sports Teams Table
    $createTeams = "CREATE TABLE IF NOT EXISTS sports_teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_name VARCHAR(255) NOT NULL,
        captain_name VARCHAR(255) NOT NULL,
        branch VARCHAR(100) NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createTeams)) {
        return ["success" => false, "error" => "Failed to create sports_teams table: " . $conn->error];
    }

    // Sports Officials Table
    $createOfficials = "CREATE TABLE IF NOT EXISTS sports_officials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        designation VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        mobile VARCHAR(20),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createOfficials)) {
        return ["success" => false, "error" => "Failed to create sports_officials table: " . $conn->error];
    }

    // Sports Links/Resources Table
    $createLinks = "CREATE TABLE IF NOT EXISTS sports_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        link_type VARCHAR(100) NOT NULL,
        link_text TEXT NOT NULL,
        link_url VARCHAR(500) NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createLinks)) {
        return ["success" => false, "error" => "Failed to create sports_links table: " . $conn->error];
    }

    // Create Indexes
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_order ON sports_gallery(display_order, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_teams_order ON sports_teams(display_order, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_officials_order ON sports_officials(display_order, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_links_type ON sports_links(link_type, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_links_order ON sports_links(display_order, is_active)");

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
            echo json_encode(["success" => false, "error" => "Entity parameter required (gallery/teams/officials/links)"]);
            break;
        }

        $tableMap = [
            'gallery' => 'sports_gallery',
            'teams' => 'sports_teams',
            'officials' => 'sports_officials',
            'links' => 'sports_links'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $searchFields = [];

            switch($entity) {
                case 'gallery':
                    $searchFields = ['image_alt'];
                    break;
                case 'teams':
                    $searchFields = ['team_name', 'captain_name', 'branch'];
                    break;
                case 'officials':
                    $searchFields = ['name', 'designation', 'email'];
                    break;
                case 'links':
                    $searchFields = ['link_type', 'link_text'];
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

            if ($entity === 'links' && !empty($_GET['link_type'])) {
                $linkType = $conn->real_escape_string($_GET['link_type']);
                $whereClauses[] = "link_type = '$linkType'";
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (gallery/teams/officials/links)"]);
            break;
        }

        $tableMap = [
            'gallery' => 'sports_gallery',
            'teams' => 'sports_teams',
            'officials' => 'sports_officials',
            'links' => 'sports_links'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];

        $requiredFields = [];
        switch($entity) {
            case 'gallery':
                $requiredFields = ['image_url'];
                break;
            case 'teams':
                $requiredFields = ['team_name', 'captain_name', 'branch'];
                break;
            case 'officials':
                $requiredFields = ['name', 'designation'];
                break;
            case 'links':
                $requiredFields = ['link_type', 'link_text', 'link_url'];
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (gallery/teams/officials/links)"]);
            break;
        }

        $tableMap = [
            'gallery' => 'sports_gallery',
            'teams' => 'sports_teams',
            'officials' => 'sports_officials',
            'links' => 'sports_links'
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
        if (!empty($_GET['link_type']) && $entity === 'links') {
            $linkType = $conn->real_escape_string($_GET['link_type']);
            $whereClauses[] = "link_type = '$linkType'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(team_name LIKE '%$keyword%' OR captain_name LIKE '%$keyword%' OR name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR link_text LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/link_type/keyword required)"]);
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (gallery/teams/officials/links)"]);
            break;
        }

        $tableMap = [
            'gallery' => 'sports_gallery',
            'teams' => 'sports_teams',
            'officials' => 'sports_officials',
            'links' => 'sports_links'
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
        if (!empty($_GET['link_type']) && $entity === 'links') {
            $linkType = $conn->real_escape_string($_GET['link_type']);
            $whereClauses[] = "link_type = '$linkType'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(team_name LIKE '%$keyword%' OR captain_name LIKE '%$keyword%' OR name LIKE '%$keyword%' OR designation LIKE '%$keyword%' OR link_text LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/link_type/keyword required)"]);
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
