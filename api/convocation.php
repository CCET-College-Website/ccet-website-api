<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createConvocationInfo = "CREATE TABLE IF NOT EXISTS `convocation_info` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year VARCHAR(10) NOT NULL UNIQUE,
        date VARCHAR(100),
        time VARCHAR(50),
        venue VARCHAR(255),
        graduates VARCHAR(100),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createGallery = "CREATE TABLE IF NOT EXISTS `convocation_gallery` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        convocation_year VARCHAR(10) NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        caption TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createSchedule = "CREATE TABLE IF NOT EXISTS `convocation_schedule` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        convocation_year VARCHAR(10) NOT NULL,
        time VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createSpeakers = "CREATE TABLE IF NOT EXISTS `convocation_speakers` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        convocation_year VARCHAR(10) NOT NULL,
        name VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        avatar_url VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createUpdates = "CREATE TABLE IF NOT EXISTS `convocation_updates` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        convocation_year VARCHAR(10) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $createVenueInfo = "CREATE TABLE IF NOT EXISTS `convocation_venue` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        convocation_year VARCHAR(10) NOT NULL,
        name VARCHAR(255) NOT NULL,
        address TEXT,
        features TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createConvocationInfo)) {
        return ["success" => false, "error" => "Failed to create convocation_info table: " . $conn->error];
    }

    if (!$conn->query($createGallery)) {
        return ["success" => false, "error" => "Failed to create convocation_gallery table: " . $conn->error];
    }

    if (!$conn->query($createSchedule)) {
        return ["success" => false, "error" => "Failed to create convocation_schedule table: " . $conn->error];
    }

    if (!$conn->query($createSpeakers)) {
        return ["success" => false, "error" => "Failed to create convocation_speakers table: " . $conn->error];
    }

    if (!$conn->query($createUpdates)) {
        return ["success" => false, "error" => "Failed to create convocation_updates table: " . $conn->error];
    }

    if (!$conn->query($createVenueInfo)) {
        return ["success" => false, "error" => "Failed to create convocation_venue table: " . $conn->error];
    }

    // Create indexes
    $conn->query("CREATE INDEX IF NOT EXISTS idx_info_year ON `convocation_info`(year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_info_active ON `convocation_info`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_year ON `convocation_gallery`(convocation_year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_order ON `convocation_gallery`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_schedule_year ON `convocation_schedule`(convocation_year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_schedule_order ON `convocation_schedule`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_speakers_year ON `convocation_speakers`(convocation_year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_speakers_order ON `convocation_speakers`(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_updates_year ON `convocation_updates`(convocation_year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_venue_year ON `convocation_venue`(convocation_year)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$table = isset($_GET['table']) ? $_GET['table'] : 'info';

if (!in_array($table, ['info', 'gallery', 'schedule', 'speakers', 'updates', 'venue'])) {
    echo json_encode(["success" => false, "error" => "Invalid table. Use 'info', 'gallery', 'schedule', 'speakers', 'updates', or 'venue'"]);
    exit;
}

$tableName = 'convocation_' . $table;

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        // Special endpoint to get all convocation data grouped by year
        if (isset($_GET['grouped']) && $_GET['grouped'] === 'true') {
            $year = isset($_GET['year']) ? $conn->real_escape_string($_GET['year']) : '2023';
            
            // Get convocation info
            $infoResult = $conn->query("SELECT * FROM `convocation_info` WHERE year = '$year' AND is_active = TRUE LIMIT 1");
            $convocationInfo = $infoResult && $infoResult->num_rows > 0 ? $infoResult->fetch_assoc() : null;

            // Get gallery images
            $galleryResult = $conn->query("SELECT * FROM `convocation_gallery` WHERE convocation_year = '$year' AND is_active = TRUE ORDER BY display_order ASC");
            $gallery = $galleryResult && $galleryResult->num_rows > 0 ? $galleryResult->fetch_all(MYSQLI_ASSOC) : [];

            // Get schedule
            $scheduleResult = $conn->query("SELECT * FROM `convocation_schedule` WHERE convocation_year = '$year' AND is_active = TRUE ORDER BY display_order ASC");
            $schedule = $scheduleResult && $scheduleResult->num_rows > 0 ? $scheduleResult->fetch_all(MYSQLI_ASSOC) : [];

            // Get speakers
            $speakersResult = $conn->query("SELECT * FROM `convocation_speakers` WHERE convocation_year = '$year' AND is_active = TRUE ORDER BY display_order ASC");
            $speakers = $speakersResult && $speakersResult->num_rows > 0 ? $speakersResult->fetch_all(MYSQLI_ASSOC) : [];

            // Get updates
            $updatesResult = $conn->query("SELECT * FROM `convocation_updates` WHERE convocation_year = '$year' AND is_active = TRUE ORDER BY display_order ASC");
            $updates = $updatesResult && $updatesResult->num_rows > 0 ? $updatesResult->fetch_all(MYSQLI_ASSOC) : [];

            // Get venue info
            $venueResult = $conn->query("SELECT * FROM `convocation_venue` WHERE convocation_year = '$year' AND is_active = TRUE LIMIT 1");
            $venue = $venueResult && $venueResult->num_rows > 0 ? $venueResult->fetch_assoc() : null;

            echo json_encode([
                "convocation_info" => $convocationInfo,
                "gallery" => $gallery,
                "schedule" => $schedule,
                "speakers" => $speakers,
                "updates" => $updates,
                "venue" => $venue
            ]);
            break;
        }

        if (isset($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $year = isset($_GET['year']) ? $conn->real_escape_string($_GET['year']) : '2023';
            
            $result = $conn->query("SELECT * FROM `$tableName` 
                                   WHERE convocation_year = '$year'
                                   AND (title LIKE '%$keyword%' 
                                   OR description LIKE '%$keyword%'
                                   OR name LIKE '%$keyword%')
                                   ORDER BY display_order ASC, id ASC");
            
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No records found with that keyword"]);
            }
        } else {
            $whereClauses = ["is_active = TRUE"];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['year'])) {
                $year = $conn->real_escape_string($_GET['year']);
                $whereClauses[] = "convocation_year = '$year'";
            }

            $query = "SELECT * FROM `$tableName`";
            
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

        $requiredFields = ['convocation_year'];
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

        $convocation_year = $conn->real_escape_string($input['convocation_year']);
        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

        if ($table === 'info') {
            if (empty($input['year'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: year"]);
                break;
            }
            
            $year = $conn->real_escape_string($input['year']);
            $date = !empty($input['date']) ? "'" . $conn->real_escape_string($input['date']) . "'" : "NULL";
            $time = !empty($input['time']) ? "'" . $conn->real_escape_string($input['time']) . "'" : "NULL";
            $venue = !empty($input['venue']) ? "'" . $conn->real_escape_string($input['venue']) . "'" : "NULL";
            $graduates = !empty($input['graduates']) ? "'" . $conn->real_escape_string($input['graduates']) . "'" : "NULL";
            
            $sql = "INSERT INTO `$tableName` (year, date, time, venue, graduates, is_active) 
                    VALUES ('$year', $date, $time, $venue, $graduates, $is_active)";
                    
        } else if ($table === 'gallery') {
            if (empty($input['image_url'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: image_url"]);
                break;
            }
            
            $image_url = $conn->real_escape_string($input['image_url']);
            $caption = !empty($input['caption']) ? "'" . $conn->real_escape_string($input['caption']) . "'" : "NULL";
            
            $sql = "INSERT INTO `$tableName` (convocation_year, image_url, caption, display_order, is_active) 
                    VALUES ('$convocation_year', '$image_url', $caption, $display_order, $is_active)";
                    
        } else if ($table === 'schedule') {
            $requiredSchedule = ['time', 'title'];
            $missingSchedule = [];
            
            foreach ($requiredSchedule as $field) {
                if (empty($input[$field])) {
                    $missingSchedule[] = $field;
                }
            }
            
            if (!empty($missingSchedule)) {
                echo json_encode(["success" => false, "error" => "Missing required fields: " . implode(", ", $missingSchedule)]);
                break;
            }
            
            $time = $conn->real_escape_string($input['time']);
            $title = $conn->real_escape_string($input['title']);
            $description = !empty($input['description']) ? "'" . $conn->real_escape_string($input['description']) . "'" : "NULL";
            
            $sql = "INSERT INTO `$tableName` (convocation_year, time, title, description, display_order, is_active) 
                    VALUES ('$convocation_year', '$time', '$title', $description, $display_order, $is_active)";
                    
        } else if ($table === 'speakers') {
            $requiredSpeakers = ['name', 'title'];
            $missingSpeakers = [];
            
            foreach ($requiredSpeakers as $field) {
                if (empty($input[$field])) {
                    $missingSpeakers[] = $field;
                }
            }
            
            if (!empty($missingSpeakers)) {
                echo json_encode(["success" => false, "error" => "Missing required fields: " . implode(", ", $missingSpeakers)]);
                break;
            }
            
            $name = $conn->real_escape_string($input['name']);
            $title = $conn->real_escape_string($input['title']);
            $description = !empty($input['description']) ? "'" . $conn->real_escape_string($input['description']) . "'" : "NULL";
            $avatar_url = !empty($input['avatar_url']) ? "'" . $conn->real_escape_string($input['avatar_url']) . "'" : "NULL";
            
            $sql = "INSERT INTO `$tableName` (convocation_year, name, title, description, avatar_url, display_order, is_active) 
                    VALUES ('$convocation_year', '$name', '$title', $description, $avatar_url, $display_order, $is_active)";
                    
        } else if ($table === 'updates') {
            if (empty($input['title'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: title"]);
                break;
            }
            
            $title = $conn->real_escape_string($input['title']);
            $description = !empty($input['description']) ? "'" . $conn->real_escape_string($input['description']) . "'" : "NULL";
            
            $sql = "INSERT INTO `$tableName` (convocation_year, title, description, display_order, is_active) 
                    VALUES ('$convocation_year', '$title', $description, $display_order, $is_active)";
                    
        } else if ($table === 'venue') {
            if (empty($input['name'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: name"]);
                break;
            }
            
            $name = $conn->real_escape_string($input['name']);
            $address = !empty($input['address']) ? "'" . $conn->real_escape_string($input['address']) . "'" : "NULL";
            $features = !empty($input['features']) ? "'" . $conn->real_escape_string($input['features']) . "'" : "NULL";
            
            $sql = "INSERT INTO `$tableName` (convocation_year, name, address, features, is_active) 
                    VALUES ('$convocation_year', '$name', $address, $features, $is_active)";
        }

        if ($conn->query($sql)) {
            $record_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $record_id,
                "message" => ucfirst($table) . " added successfully"
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
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%' OR name LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        $allowedFields = ['display_order', 'is_active'];
        
        if ($table === 'info') {
            $allowedFields = array_merge($allowedFields, ['year', 'date', 'time', 'venue', 'graduates']);
        } else if ($table === 'gallery') {
            $allowedFields = array_merge($allowedFields, ['convocation_year', 'image_url', 'caption']);
        } else if ($table === 'schedule') {
            $allowedFields = array_merge($allowedFields, ['convocation_year', 'time', 'title', 'description']);
        } else if ($table === 'speakers') {
            $allowedFields = array_merge($allowedFields, ['convocation_year', 'name', 'title', 'description', 'avatar_url']);
        } else if ($table === 'updates') {
            $allowedFields = array_merge($allowedFields, ['convocation_year', 'title', 'description']);
        } else if ($table === 'venue') {
            $allowedFields = array_merge($allowedFields, ['convocation_year', 'name', 'address', 'features']);
        }
        
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'is_active' || $key === 'display_order') {
                    $updates[] = "$key = " . (int)$value;
                    continue;
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
            $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%' OR name LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/keyword required)"]);
            break;
        }

        $sql = "UPDATE `$tableName` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE " . implode(" AND ", $whereClauses);

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