<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Department Basic Info Table
    $createDeptInfo = "CREATE TABLE IF NOT EXISTS department_info (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(50) NOT NULL UNIQUE,
        department_name VARCHAR(255) NOT NULL,
        established_year INT,
        tagline TEXT,
        about_text TEXT,
        about_image VARCHAR(500),
        vision TEXT,
        mission TEXT,
        nba_accredited BOOLEAN DEFAULT FALSE,
        nba_accreditation_date DATE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createDeptInfo)) {
        return ["success" => false, "error" => "Failed to create department_info table: " . $conn->error];
    }

    // Program Outcomes Table
    $createOutcomes = "CREATE TABLE IF NOT EXISTS program_outcomes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(50) NOT NULL,
        outcome_text TEXT NOT NULL,
        outcome_type ENUM('general', 'specific') DEFAULT 'general',
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_code) REFERENCES department_info(department_code) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createOutcomes)) {
        return ["success" => false, "error" => "Failed to create program_outcomes table: " . $conn->error];
    }

    // Program Educational Objectives Table
    $createObjectives = "CREATE TABLE IF NOT EXISTS program_objectives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(50) NOT NULL,
        objective_text TEXT NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_code) REFERENCES department_info(department_code) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createObjectives)) {
        return ["success" => false, "error" => "Failed to create program_objectives table: " . $conn->error];
    }

    // Department Events Table
    $createEvents = "CREATE TABLE IF NOT EXISTS department_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(50) NOT NULL,
        event_title VARCHAR(500) NOT NULL,
        event_description TEXT,
        event_date DATE,
        event_location VARCHAR(255),
        event_image VARCHAR(500),
        is_featured BOOLEAN DEFAULT FALSE,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_code) REFERENCES department_info(department_code) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createEvents)) {
        return ["success" => false, "error" => "Failed to create department_events table: " . $conn->error];
    }

    // Department Gallery Table
    $createGallery = "CREATE TABLE IF NOT EXISTS department_gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(50) NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        alt_text VARCHAR(255),
        caption TEXT,
        gallery_type ENUM('carousel', 'tour', 'event') DEFAULT 'carousel',
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_code) REFERENCES department_info(department_code) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createGallery)) {
        return ["success" => false, "error" => "Failed to create department_gallery table: " . $conn->error];
    }

    // Department Quick Links Table
    $createQuickLinks = "CREATE TABLE IF NOT EXISTS department_quick_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(50) NOT NULL,
        link_title VARCHAR(255) NOT NULL,
        link_url VARCHAR(500) NOT NULL,
        icon_svg TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_code) REFERENCES department_info(department_code) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createQuickLinks)) {
        return ["success" => false, "error" => "Failed to create department_quick_links table: " . $conn->error];
    }

    // Department Courses Table
    $createCourses = "CREATE TABLE IF NOT EXISTS department_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_code VARCHAR(50) NOT NULL,
        course_name VARCHAR(255) NOT NULL,
        course_description TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_code) REFERENCES department_info(department_code) ON DELETE CASCADE
    )";
    
    if (!$conn->query($createCourses)) {
        return ["success" => false, "error" => "Failed to create department_courses table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_dept_code ON department_info(department_code)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_outcomes_dept ON program_outcomes(department_code, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_objectives_dept ON program_objectives(department_code, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_events_dept ON department_events(department_code, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_events_featured ON department_events(is_featured)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_dept ON department_gallery(department_code, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_type ON department_gallery(gallery_type)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_links_dept ON department_quick_links(department_code, is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_courses_dept ON department_courses(department_code, is_active)");

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
            echo json_encode(["success" => false, "error" => "Entity parameter required (info/outcomes/objectives/events/gallery/links/courses)"]);
            break;
        }

        $tableMap = [
            'info' => 'department_info',
            'outcomes' => 'program_outcomes',
            'objectives' => 'program_objectives',
            'events' => 'department_events',
            'gallery' => 'department_gallery',
            'links' => 'department_quick_links',
            'courses' => 'department_courses'
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
                case 'info':
                    $searchFields = ['department_name', 'department_code', 'about_text', 'vision', 'mission'];
                    break;
                case 'outcomes':
                    $searchFields = ['outcome_text'];
                    break;
                case 'objectives':
                    $searchFields = ['objective_text'];
                    break;
                case 'events':
                    $searchFields = ['event_title', 'event_description', 'event_location'];
                    break;
                case 'gallery':
                    $searchFields = ['alt_text', 'caption'];
                    break;
                case 'links':
                    $searchFields = ['link_title', 'link_url'];
                    break;
                case 'courses':
                    $searchFields = ['course_name', 'course_description'];
                    break;
            }
            
            $searchConditions = array_map(function($field) use ($keyword) {
                return "$field LIKE '%$keyword%'";
            }, $searchFields);
            
            $whereClause = "(" . implode(" OR ", $searchConditions) . ")";
            
            if (!empty($_GET['department_code'])) {
                $deptCode = $conn->real_escape_string($_GET['department_code']);
                $whereClause .= " AND department_code = '$deptCode'";
            }
            
            $result = $conn->query("SELECT * FROM $table WHERE $whereClause ORDER BY display_order ASC, id ASC");
            
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

            if (!empty($_GET['department_code'])) {
                $deptCode = $conn->real_escape_string($_GET['department_code']);
                $whereClauses[] = "department_code = '$deptCode'";
            }

            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            if ($entity === 'outcomes' && !empty($_GET['outcome_type'])) {
                $outcomeType = $conn->real_escape_string($_GET['outcome_type']);
                $whereClauses[] = "outcome_type = '$outcomeType'";
            }

            if ($entity === 'events') {
                if (isset($_GET['is_featured'])) {
                    $isFeatured = $_GET['is_featured'] === 'true' ? 1 : 0;
                    $whereClauses[] = "is_featured = $isFeatured";
                }
                if (!empty($_GET['event_date'])) {
                    $eventDate = $conn->real_escape_string($_GET['event_date']);
                    $whereClauses[] = "event_date = '$eventDate'";
                }
            }

            if ($entity === 'gallery' && !empty($_GET['gallery_type'])) {
                $galleryType = $conn->real_escape_string($_GET['gallery_type']);
                $whereClauses[] = "gallery_type = '$galleryType'";
            }

            if ($entity === 'info' && isset($_GET['nba_accredited'])) {
                $nbaAccredited = $_GET['nba_accredited'] === 'true' ? 1 : 0;
                $whereClauses[] = "nba_accredited = $nbaAccredited";
            }

            $query = "SELECT * FROM $table";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            
            if ($entity === 'info') {
                $query .= " ORDER BY id ASC";
            } else {
                $query .= " ORDER BY display_order ASC, id ASC";
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

        if (!$entity) {
            echo json_encode(["success" => false, "error" => "Entity parameter required (info/outcomes/objectives/events/gallery/links/courses)"]);
            break;
        }

        $tableMap = [
            'info' => 'department_info',
            'outcomes' => 'program_outcomes',
            'objectives' => 'program_objectives',
            'events' => 'department_events',
            'gallery' => 'department_gallery',
            'links' => 'department_quick_links',
            'courses' => 'department_courses'
        ];

        if (!isset($tableMap[$entity])) {
            echo json_encode(["success" => false, "error" => "Invalid entity type"]);
            break;
        }

        $table = $tableMap[$entity];

        $requiredFields = [];
        switch($entity) {
            case 'info':
                $requiredFields = ['department_code', 'department_name'];
                break;
            case 'outcomes':
                $requiredFields = ['department_code', 'outcome_text'];
                break;
            case 'objectives':
                $requiredFields = ['department_code', 'objective_text'];
                break;
            case 'events':
                $requiredFields = ['department_code', 'event_title'];
                break;
            case 'gallery':
                $requiredFields = ['department_code', 'image_url'];
                break;
            case 'links':
                $requiredFields = ['department_code', 'link_title', 'link_url'];
                break;
            case 'courses':
                $requiredFields = ['department_code', 'course_name'];
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
                } else if (in_array($key, ['display_order', 'is_active', 'is_featured', 'nba_accredited', 'established_year'])) {
                    $values[] = (int)$value;
                } else {
                    $escapedValue = $conn->real_escape_string($value);
                    $values[] = "'$escapedValue'";
                }
            }
        }

        if (!in_array('display_order', $columns) && $entity !== 'info') {
            $columns[] = 'display_order';
            $values[] = '0';
        }
        if (!in_array('is_active', $columns)) {
            $columns[] = 'is_active';
            $values[] = '1';
        }
        if ($entity === 'outcomes' && !in_array('outcome_type', $columns)) {
            $columns[] = 'outcome_type';
            $values[] = "'general'";
        }
        if ($entity === 'gallery' && !in_array('gallery_type', $columns)) {
            $columns[] = 'gallery_type';
            $values[] = "'carousel'";
        }
        if ($entity === 'events' && !in_array('is_featured', $columns)) {
            $columns[] = 'is_featured';
            $values[] = '0';
        }

        $sql = "INSERT INTO $table (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";

        if ($conn->query($sql)) {
            $id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $id,
                "entity" => $entity,
                "department_code" => $input['department_code']
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (info/outcomes/objectives/events/gallery/links/courses)"]);
            break;
        }

        $tableMap = [
            'info' => 'department_info',
            'outcomes' => 'program_outcomes',
            'objectives' => 'program_objectives',
            'events' => 'department_events',
            'gallery' => 'department_gallery',
            'links' => 'department_quick_links',
            'courses' => 'department_courses'
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
        if (!empty($_GET['department_code'])) {
            $deptCode = $conn->real_escape_string($_GET['department_code']);
            $whereClauses[] = "department_code = '$deptCode'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department_name LIKE '%$keyword%' OR event_title LIKE '%$keyword%' OR link_title LIKE '%$keyword%' OR course_name LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department_code/keyword required)"]);
            break;
        }

        $updates = [];

        foreach ($input as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at' && $key !== 'updated_at') {
                if ($value === null || $value === '') {
                    $updates[] = "$key = NULL";
                } else if (in_array($key, ['display_order', 'is_active', 'is_featured', 'nba_accredited', 'established_year'])) {
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
            echo json_encode(["success" => false, "error" => "Entity parameter required (info/outcomes/objectives/events/gallery/links/courses)"]);
            break;
        }

        $tableMap = [
            'info' => 'department_info',
            'outcomes' => 'program_outcomes',
            'objectives' => 'program_objectives',
            'events' => 'department_events',
            'gallery' => 'department_gallery',
            'links' => 'department_quick_links',
            'courses' => 'department_courses'
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
        if (!empty($_GET['department_code'])) {
            $deptCode = $conn->real_escape_string($_GET['department_code']);
            $whereClauses[] = "department_code = '$deptCode'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(department_name LIKE '%$keyword%' OR event_title LIKE '%$keyword%' OR link_title LIKE '%$keyword%' OR course_name LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/department_code/keyword required)"]);
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