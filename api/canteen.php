<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Table for menu items
    $createMenus = "CREATE TABLE IF NOT EXISTS canteen_menus (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        image_url VARCHAR(500) NOT NULL,
        menu_type VARCHAR(50) DEFAULT 'regular',
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createMenus)) {
        return ["success" => false, "error" => "Failed to create canteen_menus table: " . $conn->error];
    }

    // Table for gallery images
    $createGallery = "CREATE TABLE IF NOT EXISTS canteen_gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_url VARCHAR(500) NOT NULL,
        caption VARCHAR(255),
        alt_text VARCHAR(255),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createGallery)) {
        return ["success" => false, "error" => "Failed to create canteen_gallery table: " . $conn->error];
    }

    // Table for operating hours
    $createHours = "CREATE TABLE IF NOT EXISTS canteen_hours (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day_of_week VARCHAR(20) NOT NULL,
        opening_time TIME,
        closing_time TIME,
        is_closed BOOLEAN DEFAULT FALSE,
        special_note TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_day (day_of_week)
    )";
    
    if (!$conn->query($createHours)) {
        return ["success" => false, "error" => "Failed to create canteen_hours table: " . $conn->error];
    }

    // Create indexes
    $conn->query("CREATE INDEX IF NOT EXISTS idx_menu_type ON canteen_menus(menu_type)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_menu_order ON canteen_menus(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_menu_active ON canteen_menus(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_order ON canteen_gallery(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_active ON canteen_gallery(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hours_day ON canteen_hours(day_of_week)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_hours_order ON canteen_hours(display_order)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Get the resource type from URL parameter
$resource = isset($_GET['resource']) ? $_GET['resource'] : 'menus';

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if ($resource === 'menus') {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }
            if (!empty($_GET['menu_type'])) {
                $menu_type = $conn->real_escape_string($_GET['menu_type']);
                $whereClauses[] = "menu_type = '$menu_type'";
            }
            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }
            if (!empty($_GET['keyword'])) {
                $keyword = $conn->real_escape_string($_GET['keyword']);
                $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            }

            $query = "SELECT * FROM canteen_menus";
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY display_order ASC, id ASC";

            $result = $conn->query($query);
            if ($result) {
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } elseif ($resource === 'gallery') {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }
            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }
            if (!empty($_GET['keyword'])) {
                $keyword = $conn->real_escape_string($_GET['keyword']);
                $whereClauses[] = "(caption LIKE '%$keyword%' OR alt_text LIKE '%$keyword%')";
            }

            $query = "SELECT * FROM canteen_gallery";
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY display_order ASC, id ASC";

            $result = $conn->query($query);
            if ($result) {
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } elseif ($resource === 'hours') {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }
            if (!empty($_GET['day_of_week'])) {
                $day = $conn->real_escape_string($_GET['day_of_week']);
                $whereClauses[] = "day_of_week = '$day'";
            }
            if (isset($_GET['is_closed'])) {
                $is_closed = $_GET['is_closed'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_closed = $is_closed";
            }
            if (isset($_GET['is_active'])) {
                $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
                $whereClauses[] = "is_active = $is_active";
            }

            $query = "SELECT * FROM canteen_hours";
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY display_order ASC, id ASC";

            $result = $conn->query($query);
            if ($result) {
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } else {
            echo json_encode(["success" => false, "error" => "Invalid resource. Use 'menus', 'gallery', or 'hours'"]);
        }
        break;

    case 'POST':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if ($resource === 'menus') {
            $requiredFields = ['title', 'image_url'];
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

            $title = $conn->real_escape_string($input['title']);
            $description = isset($input['description']) ? $conn->real_escape_string($input['description']) : null;
            $image_url = $conn->real_escape_string($input['image_url']);
            $menu_type = isset($input['menu_type']) ? $conn->real_escape_string($input['menu_type']) : 'regular';
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            $sql = "INSERT INTO canteen_menus (title, description, image_url, menu_type, display_order, is_active) 
                    VALUES ('$title', " . ($description ? "'$description'" : "NULL") . ", '$image_url', '$menu_type', $display_order, $is_active)";

            if ($conn->query($sql)) {
                echo json_encode([
                    "success" => true,
                    "menu_id" => $conn->insert_id,
                    "title" => $input['title']
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } elseif ($resource === 'gallery') {
            if (empty($input['image_url'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: image_url"]);
                break;
            }

            $image_url = $conn->real_escape_string($input['image_url']);
            $caption = isset($input['caption']) ? $conn->real_escape_string($input['caption']) : null;
            $alt_text = isset($input['alt_text']) ? $conn->real_escape_string($input['alt_text']) : null;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            $sql = "INSERT INTO canteen_gallery (image_url, caption, alt_text, display_order, is_active) 
                    VALUES ('$image_url', " . ($caption ? "'$caption'" : "NULL") . ", " . 
                    ($alt_text ? "'$alt_text'" : "NULL") . ", $display_order, $is_active)";

            if ($conn->query($sql)) {
                echo json_encode([
                    "success" => true,
                    "gallery_id" => $conn->insert_id
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } elseif ($resource === 'hours') {
            if (empty($input['day_of_week'])) {
                echo json_encode(["success" => false, "error" => "Missing required field: day_of_week"]);
                break;
            }

            $day_of_week = $conn->real_escape_string($input['day_of_week']);
            $opening_time = isset($input['opening_time']) ? $conn->real_escape_string($input['opening_time']) : null;
            $closing_time = isset($input['closing_time']) ? $conn->real_escape_string($input['closing_time']) : null;
            $is_closed = isset($input['is_closed']) ? (int)$input['is_closed'] : 0;
            $special_note = isset($input['special_note']) ? $conn->real_escape_string($input['special_note']) : null;
            $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
            $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;

            $sql = "INSERT INTO canteen_hours (day_of_week, opening_time, closing_time, is_closed, special_note, display_order, is_active) 
                    VALUES ('$day_of_week', " . ($opening_time ? "'$opening_time'" : "NULL") . ", " . 
                    ($closing_time ? "'$closing_time'" : "NULL") . ", $is_closed, " . 
                    ($special_note ? "'$special_note'" : "NULL") . ", $display_order, $is_active)";

            if ($conn->query($sql)) {
                echo json_encode([
                    "success" => true,
                    "hours_id" => $conn->insert_id,
                    "day_of_week" => $input['day_of_week']
                ]);
            } else {
                echo json_encode(["success" => false, "error" => $conn->error]);
            }

        } else {
            echo json_encode(["success" => false, "error" => "Invalid resource"]);
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
            if ($resource === 'menus') {
                $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            } elseif ($resource === 'gallery') {
                $whereClauses[] = "(caption LIKE '%$keyword%' OR alt_text LIKE '%$keyword%')";
            } elseif ($resource === 'hours') {
                $whereClauses[] = "(day_of_week LIKE '%$keyword%' OR special_note LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id or keyword required)"]);
            break;
        }

        $updates = [];
        $table = '';
        $allowedFields = [];

        if ($resource === 'menus') {
            $table = 'canteen_menus';
            $allowedFields = ['title', 'description', 'image_url', 'menu_type', 'display_order', 'is_active'];
        } elseif ($resource === 'gallery') {
            $table = 'canteen_gallery';
            $allowedFields = ['image_url', 'caption', 'alt_text', 'display_order', 'is_active'];
        } elseif ($resource === 'hours') {
            $table = 'canteen_hours';
            $allowedFields = ['day_of_week', 'opening_time', 'closing_time', 'is_closed', 'special_note', 'display_order', 'is_active'];
        } else {
            echo json_encode(["success" => false, "error" => "Invalid resource"]);
            break;
        }

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($value === null || $value === '') {
                    $updates[] = "$key = NULL";
                } else if (in_array($key, ['display_order', 'is_active', 'is_closed'])) {
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

        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            if ($resource === 'menus') {
                $whereClauses[] = "(title LIKE '%$keyword%' OR description LIKE '%$keyword%')";
            } elseif ($resource === 'gallery') {
                $whereClauses[] = "(caption LIKE '%$keyword%' OR alt_text LIKE '%$keyword%')";
            } elseif ($resource === 'hours') {
                $whereClauses[] = "(day_of_week LIKE '%$keyword%' OR special_note LIKE '%$keyword%')";
            }
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id or keyword required)"]);
            break;
        }

        $table = '';
        if ($resource === 'menus') {
            $table = 'canteen_menus';
        } elseif ($resource === 'gallery') {
            $table = 'canteen_gallery';
        } elseif ($resource === 'hours') {
            $table = 'canteen_hours';
        } else {
            echo json_encode(["success" => false, "error" => "Invalid resource"]);
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