<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Create programmes table
    $createProgrammes = "CREATE TABLE IF NOT EXISTS `programmes` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        programme_type ENUM('bachelor', 'leet', 'phd') NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        intro_text TEXT,
        additional_info TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createProgrammes)) {
        return ["success" => false, "error" => "Failed to create programmes table: " . $conn->error];
    }

    // Create seat_matrix table for bachelor programmes
    $createSeatMatrix = "CREATE TABLE IF NOT EXISTS `seat_matrix` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        programme_id INT NOT NULL,
        branch VARCHAR(255) NOT NULL,
        seats INT NOT NULL,
        display_order INT DEFAULT 0,
        FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createSeatMatrix)) {
        return ["success" => false, "error" => "Failed to create seat_matrix table: " . $conn->error];
    }

    // Create important_links table
    $createLinks = "CREATE TABLE IF NOT EXISTS `important_links` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        programme_id INT NOT NULL,
        link_text VARCHAR(255) NOT NULL,
        link_url TEXT NOT NULL,
        display_order INT DEFAULT 0,
        FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createLinks)) {
        return ["success" => false, "error" => "Failed to create important_links table: " . $conn->error];
    }

    // Create notes table
    $createNotes = "CREATE TABLE IF NOT EXISTS `programme_notes` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        programme_id INT NOT NULL,
        note_text TEXT NOT NULL,
        display_order INT DEFAULT 0,
        FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createNotes)) {
        return ["success" => false, "error" => "Failed to create programme_notes table: " . $conn->error];
    }

    // Create indexes
    $conn->query("CREATE INDEX IF NOT EXISTS idx_programmes_type ON `programmes`(programme_type)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_programmes_status ON `programmes`(status)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_seat_matrix_programme ON `seat_matrix`(programme_id)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_links_programme ON `important_links`(programme_id)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_notes_programme ON `programme_notes`(programme_id)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function isValidURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
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

        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            $result = $conn->query("SELECT * FROM `programmes` WHERE id = $id");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_assoc();
                
                if ($data['programme_type'] === 'bachelor') {
                    $seatResult = $conn->query("SELECT * FROM `seat_matrix` WHERE programme_id = $id ORDER BY display_order, id");
                    $data['seat_matrix'] = $seatResult ? $seatResult->fetch_all(MYSQLI_ASSOC) : [];
                }
                
                $linksResult = $conn->query("SELECT * FROM `important_links` WHERE programme_id = $id ORDER BY display_order, id");
                $data['important_links'] = $linksResult ? $linksResult->fetch_all(MYSQLI_ASSOC) : [];
                
                $notesResult = $conn->query("SELECT * FROM `programme_notes` WHERE programme_id = $id ORDER BY display_order, id");
                $data['notes'] = $notesResult ? $notesResult->fetch_all(MYSQLI_ASSOC) : [];
                
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "Programme not found"]);
            }
        }
        elseif (isset($_GET['type'])) {
            $type = $conn->real_escape_string($_GET['type']);
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : 'active';
            
            $query = "SELECT * FROM `programmes` WHERE programme_type = '$type' AND status = '$status' ORDER BY display_order, id";
            $result = $conn->query($query);
            
            if ($result) {
                $programmes = [];
                while ($row = $result->fetch_assoc()) {
                    $pid = $row['id'];
                    
                    if ($row['programme_type'] === 'bachelor') {
                        $seatResult = $conn->query("SELECT * FROM `seat_matrix` WHERE programme_id = $pid ORDER BY display_order, id");
                        $row['seat_matrix'] = $seatResult ? $seatResult->fetch_all(MYSQLI_ASSOC) : [];
                    }
                    
                    $linksResult = $conn->query("SELECT * FROM `important_links` WHERE programme_id = $pid ORDER BY display_order, id");
                    $row['important_links'] = $linksResult ? $linksResult->fetch_all(MYSQLI_ASSOC) : [];
                    
                    $notesResult = $conn->query("SELECT * FROM `programme_notes` WHERE programme_id = $pid ORDER BY display_order, id");
                    $row['notes'] = $notesResult ? $notesResult->fetch_all(MYSQLI_ASSOC) : [];
                    
                    $programmes[] = $row;
                }
                echo json_encode($programmes);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }
        }
        else {
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : null;
            $query = "SELECT * FROM `programmes`";
            
            if ($status) {
                $query .= " WHERE status = '$status'";
            }
            $query .= " ORDER BY programme_type, display_order, id";
            
            $result = $conn->query($query);
            
            if ($result) {
                $programmes = [];
                while ($row = $result->fetch_assoc()) {
                    $pid = $row['id'];
                    
                    if ($row['programme_type'] === 'bachelor') {
                        $seatResult = $conn->query("SELECT * FROM `seat_matrix` WHERE programme_id = $pid ORDER BY display_order, id");
                        $row['seat_matrix'] = $seatResult ? $seatResult->fetch_all(MYSQLI_ASSOC) : [];
                    }
                    
                    $linksResult = $conn->query("SELECT * FROM `important_links` WHERE programme_id = $pid ORDER BY display_order, id");
                    $row['important_links'] = $linksResult ? $linksResult->fetch_all(MYSQLI_ASSOC) : [];
                    
                    $notesResult = $conn->query("SELECT * FROM `programme_notes` WHERE programme_id = $pid ORDER BY display_order, id");
                    $row['notes'] = $notesResult ? $notesResult->fetch_all(MYSQLI_ASSOC) : [];
                    
                    $programmes[] = $row;
                }
                echo json_encode($programmes);
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

        if (empty($input['programme_type']) || empty($input['title'])) {
            echo json_encode(["success" => false, "error" => "programme_type and title are required"]);
            break;
        }

        $validTypes = ['bachelor', 'leet', 'phd'];
        if (!in_array($input['programme_type'], $validTypes)) {
            echo json_encode(["success" => false, "error" => "Invalid programme_type. Must be: bachelor, leet, or phd"]);
            break;
        }

        $conn->begin_transaction();

        try {
            $programme_type = $conn->real_escape_string($input['programme_type']);
            $title = $conn->real_escape_string($input['title']);
            $description = isset($input['description']) ? $conn->real_escape_string($input['description']) : null;
            $intro_text = isset($input['intro_text']) ? $conn->real_escape_string($input['intro_text']) : null;
            $additional_info = isset($input['additional_info']) ? $conn->real_escape_string($input['additional_info']) : null;
            $status = isset($input['status']) ? $conn->real_escape_string($input['status']) : 'active';
            $display_order = isset($input['display_order']) ? intval($input['display_order']) : 0;

            $sql = "INSERT INTO `programmes` 
                    (programme_type, title, description, intro_text, additional_info, status, display_order)
                    VALUES ('$programme_type', '$title', " . 
                    ($description ? "'$description'" : "NULL") . ", " .
                    ($intro_text ? "'$intro_text'" : "NULL") . ", " .
                    ($additional_info ? "'$additional_info'" : "NULL") . ", '$status', $display_order)";

            if (!$conn->query($sql)) {
                throw new Exception("Failed to insert programme: " . $conn->error);
            }

            $programme_id = $conn->insert_id;

            if ($programme_type === 'bachelor' && !empty($input['seat_matrix'])) {
                foreach ($input['seat_matrix'] as $index => $seat) {
                    if (!empty($seat['branch']) && isset($seat['seats'])) {
                        $branch = $conn->real_escape_string($seat['branch']);
                        $seats = intval($seat['seats']);
                        $order = isset($seat['display_order']) ? intval($seat['display_order']) : $index;
                        
                        $seatSql = "INSERT INTO `seat_matrix` (programme_id, branch, seats, display_order) 
                                   VALUES ($programme_id, '$branch', $seats, $order)";
                        if (!$conn->query($seatSql)) {
                            throw new Exception("Failed to insert seat matrix: " . $conn->error);
                        }
                    }
                }
            }

            if (!empty($input['important_links'])) {
                foreach ($input['important_links'] as $index => $link) {
                    if (!empty($link['link_text']) && !empty($link['link_url'])) {
                        if (!isValidURL($link['link_url'])) {
                            throw new Exception("Invalid URL: " . $link['link_url']);
                        }
                        
                        $link_text = $conn->real_escape_string($link['link_text']);
                        $link_url = $conn->real_escape_string($link['link_url']);
                        $order = isset($link['display_order']) ? intval($link['display_order']) : $index;
                        
                        $linkSql = "INSERT INTO `important_links` (programme_id, link_text, link_url, display_order) 
                                   VALUES ($programme_id, '$link_text', '$link_url', $order)";
                        if (!$conn->query($linkSql)) {
                            throw new Exception("Failed to insert link: " . $conn->error);
                        }
                    }
                }
            }

            if (!empty($input['notes'])) {
                foreach ($input['notes'] as $index => $note) {
                    if (!empty($note['note_text'])) {
                        $note_text = $conn->real_escape_string($note['note_text']);
                        $order = isset($note['display_order']) ? intval($note['display_order']) : $index;
                        
                        $noteSql = "INSERT INTO `programme_notes` (programme_id, note_text, display_order) 
                                   VALUES ($programme_id, '$note_text', $order)";
                        if (!$conn->query($noteSql)) {
                            throw new Exception("Failed to insert note: " . $conn->error);
                        }
                    }
                }
            }

            $conn->commit();
            echo json_encode([
                "success" => true, 
                "id" => $programme_id,
                "programme_type" => $input['programme_type'],
                "title" => $input['title']
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        break;

    case 'PATCH':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "Programme ID is required"]);
            break;
        }

        $id = intval($_GET['id']);

        $conn->begin_transaction();

        try {
            $allowedFields = ['programme_type', 'title', 'description', 'intro_text', 'additional_info', 'status', 'display_order'];
            $updates = [];

            foreach ($input as $key => $value) {
                if (in_array($key, $allowedFields) && $key !== 'programme_type') {
                    if ($value === null || $value === '') {
                        $updates[] = "$key = NULL";
                    } else {
                        if ($key === 'display_order') {
                            $updates[] = "$key = " . intval($value);
                        } else {
                            $value = $conn->real_escape_string($value);
                            $updates[] = "$key = '$value'";
                        }
                    }
                }
            }

            if (!empty($updates)) {
                $updates[] = "updated_at = CURRENT_TIMESTAMP";
                $sql = "UPDATE `programmes` SET " . implode(", ", $updates) . " WHERE id = $id";
                
                if (!$conn->query($sql)) {
                    throw new Exception("Failed to update programme: " . $conn->error);
                }
            }

            if (isset($input['seat_matrix']) && is_array($input['seat_matrix'])) {
                $conn->query("DELETE FROM `seat_matrix` WHERE programme_id = $id");
                
                foreach ($input['seat_matrix'] as $index => $seat) {
                    if (!empty($seat['branch']) && isset($seat['seats'])) {
                        $branch = $conn->real_escape_string($seat['branch']);
                        $seats = intval($seat['seats']);
                        $order = isset($seat['display_order']) ? intval($seat['display_order']) : $index;
                        
                        $seatSql = "INSERT INTO `seat_matrix` (programme_id, branch, seats, display_order) 
                                   VALUES ($id, '$branch', $seats, $order)";
                        if (!$conn->query($seatSql)) {
                            throw new Exception("Failed to insert seat matrix: " . $conn->error);
                        }
                    }
                }
            }

            if (isset($input['important_links']) && is_array($input['important_links'])) {
                $conn->query("DELETE FROM `important_links` WHERE programme_id = $id");
                
                foreach ($input['important_links'] as $index => $link) {
                    if (!empty($link['link_text']) && !empty($link['link_url'])) {
                        if (!isValidURL($link['link_url'])) {
                            throw new Exception("Invalid URL: " . $link['link_url']);
                        }
                        
                        $link_text = $conn->real_escape_string($link['link_text']);
                        $link_url = $conn->real_escape_string($link['link_url']);
                        $order = isset($link['display_order']) ? intval($link['display_order']) : $index;
                        
                        $linkSql = "INSERT INTO `important_links` (programme_id, link_text, link_url, display_order) 
                                   VALUES ($id, '$link_text', '$link_url', $order)";
                        if (!$conn->query($linkSql)) {
                            throw new Exception("Failed to insert link: " . $conn->error);
                        }
                    }
                }
            }

            if (isset($input['notes']) && is_array($input['notes'])) {
                $conn->query("DELETE FROM `programme_notes` WHERE programme_id = $id");
                
                foreach ($input['notes'] as $index => $note) {
                    if (!empty($note['note_text'])) {
                        $note_text = $conn->real_escape_string($note['note_text']);
                        $order = isset($note['display_order']) ? intval($note['display_order']) : $index;
                        
                        $noteSql = "INSERT INTO `programme_notes` (programme_id, note_text, display_order) 
                                   VALUES ($id, '$note_text', $order)";
                        if (!$conn->query($noteSql)) {
                            throw new Exception("Failed to insert note: " . $conn->error);
                        }
                    }
                }
            }

            $conn->commit();
            echo json_encode(["success" => true, "message" => "Programme updated successfully"]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "Programme ID is required"]);
            break;
        }

        $id = intval($_GET['id']);
        
        $sql = "DELETE FROM `programmes` WHERE id = $id";

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