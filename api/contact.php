<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    // Table for email contacts
    $createEmails = "CREATE TABLE IF NOT EXISTS `contact-emails` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_display_order (display_order),
        INDEX idx_is_active (is_active)
    )";
    
    if (!$conn->query($createEmails)) {
        return ["success" => false, "error" => "Failed to create contact-emails table: " . $conn->error];
    }

    // Table for phone contacts
    $createPhones = "CREATE TABLE IF NOT EXISTS `contact-phones` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(255) NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_display_order (display_order),
        INDEX idx_is_active (is_active)
    )";
    
    if (!$conn->query($createPhones)) {
        return ["success" => false, "error" => "Failed to create contact-phones table: " . $conn->error];
    }

    // Table for social media links
    $createSocials = "CREATE TABLE IF NOT EXISTS `contact-socials` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        platform VARCHAR(100) NOT NULL,
        url VARCHAR(500) NOT NULL,
        icon_url VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_display_order (display_order),
        INDEX idx_is_active (is_active)
    )";
    
    if (!$conn->query($createSocials)) {
        return ["success" => false, "error" => "Failed to create contact-socials table: " . $conn->error];
    }

    // Table for address/connectivity information
    $createAddress = "CREATE TABLE IF NOT EXISTS `contact-address` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mode ENUM('Roadways', 'Railways', 'Airways') NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_mode (mode),
        INDEX idx_display_order (display_order),
        INDEX idx_is_active (is_active)
    )";
    
    if (!$conn->query($createAddress)) {
        return ["success" => false, "error" => "Failed to create contact-address table: " . $conn->error];
    }

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

$resource = isset($_GET['resource']) ? $_GET['resource'] : '';

switch ($method) {
    case 'GET':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $whereClauses = [];
        $table = '';
        $orderBy = 'display_order ASC, id ASC';

        switch ($resource) {
            case 'emails':
                $table = 'contact-emails';
                if (!empty($_GET['name'])) {
                    $name = $conn->real_escape_string($_GET['name']);
                    $whereClauses[] = "name LIKE '%$name%'";
                }
                if (!empty($_GET['email'])) {
                    $email = $conn->real_escape_string($_GET['email']);
                    $whereClauses[] = "email LIKE '%$email%'";
                }
                break;

            case 'phones':
                $table = 'contact-phones';
                if (!empty($_GET['name'])) {
                    $name = $conn->real_escape_string($_GET['name']);
                    $whereClauses[] = "name LIKE '%$name%'";
                }
                break;

            case 'socials':
                $table = 'contact-socials';
                if (!empty($_GET['platform'])) {
                    $platform = $conn->real_escape_string($_GET['platform']);
                    $whereClauses[] = "platform = '$platform'";
                }
                break;

            case 'address':
                $table = 'contact-address';
                if (!empty($_GET['mode'])) {
                    $mode = $conn->real_escape_string($_GET['mode']);
                    $whereClauses[] = "mode = '$mode'";
                }
                break;

            default:
                echo json_encode(["success" => false, "error" => "Invalid resource. Use 'emails', 'phones', 'socials', or 'address'"]);
                exit;
        }

        if (!empty($_GET['id'])) {
            $id = (int)$_GET['id'];
            $whereClauses[] = "id = $id";
        }
        if (isset($_GET['is_active'])) {
            $is_active = $_GET['is_active'] === 'true' ? 1 : 0;
            $whereClauses[] = "is_active = $is_active";
        }

        $query = "SELECT * FROM `$table`";
        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $query .= " ORDER BY $orderBy";

        $result = $conn->query($query);
        if ($result) {
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'POST':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $table = '';
        $fields = [];
        $values = [];

        switch ($resource) {
            case 'emails':
                if (empty($input['name']) || empty($input['email'])) {
                    echo json_encode(["success" => false, "error" => "Missing required fields: name, email"]);
                    exit;
                }
                $table = 'contact-emails';
                $fields = ['name', 'email'];
                $values = [
                    $conn->real_escape_string($input['name']),
                    $conn->real_escape_string($input['email'])
                ];
                break;

            case 'phones':
                if (empty($input['name']) || empty($input['phone'])) {
                    echo json_encode(["success" => false, "error" => "Missing required fields: name, phone"]);
                    exit;
                }
                $table = 'contact-phones';
                $fields = ['name', 'phone'];
                $values = [
                    $conn->real_escape_string($input['name']),
                    $conn->real_escape_string($input['phone'])
                ];
                break;

            case 'socials':
                if (empty($input['platform']) || empty($input['url'])) {
                    echo json_encode(["success" => false, "error" => "Missing required fields: platform, url"]);
                    exit;
                }
                $table = 'contact-socials';
                $fields = ['platform', 'url'];
                $values = [
                    $conn->real_escape_string($input['platform']),
                    $conn->real_escape_string($input['url'])
                ];
                if (!empty($input['icon_url'])) {
                    $fields[] = 'icon_url';
                    $values[] = $conn->real_escape_string($input['icon_url']);
                }
                break;

            case 'address':
                if (empty($input['mode']) || empty($input['title']) || empty($input['content'])) {
                    echo json_encode(["success" => false, "error" => "Missing required fields: mode, title, content"]);
                    exit;
                }
                $table = 'contact-address';
                $fields = ['mode', 'title', 'content'];
                $values = [
                    $conn->real_escape_string($input['mode']),
                    $conn->real_escape_string($input['title']),
                    $conn->real_escape_string($input['content'])
                ];
                break;

            default:
                echo json_encode(["success" => false, "error" => "Invalid resource"]);
                exit;
        }

        $display_order = isset($input['display_order']) ? (int)$input['display_order'] : 0;
        $is_active = isset($input['is_active']) ? (int)$input['is_active'] : 1;
        
        $fields[] = 'display_order';
        $fields[] = 'is_active';
        $values[] = $display_order;
        $values[] = $is_active;

        $fieldList = implode(', ', $fields);
        $valuePlaceholders = implode("', '", array_map(function($v) { return is_int($v) ? $v : $v; }, $values));
        
        $sql = "INSERT INTO `$table` ($fieldList) VALUES ('$valuePlaceholders')";
        $sql = str_replace("'$display_order'", $display_order, $sql);
        $sql = str_replace("'$is_active'", $is_active, $sql);

        if ($conn->query($sql)) {
            echo json_encode([
                "success" => true,
                "id" => $conn->insert_id,
                "resource" => $resource
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

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID required for update"]);
            break;
        }

        $id = (int)$_GET['id'];
        $table = '';

        switch ($resource) {
            case 'emails':
                $table = 'contact-emails';
                break;
            case 'phones':
                $table = 'contact-phones';
                break;
            case 'socials':
                $table = 'contact-socials';
                break;
            case 'address':
                $table = 'contact-address';
                break;
            default:
                echo json_encode(["success" => false, "error" => "Invalid resource"]);
                exit;
        }

        $updates = [];
        foreach ($input as $key => $value) {
            if (in_array($key, ['name', 'email', 'phone', 'platform', 'url', 'icon_url', 'mode', 'title', 'content'])) {
                $value = $conn->real_escape_string($value);
                $updates[] = "$key = '$value'";
            } elseif (in_array($key, ['display_order', 'is_active'])) {
                $updates[] = "$key = " . (int)$value;
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE `$table` SET " . implode(", ", $updates) . " WHERE id = $id";

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

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID required for delete"]);
            break;
        }

        $id = (int)$_GET['id'];
        $table = '';

        switch ($resource) {
            case 'emails':
                $table = 'contact-emails';
                break;
            case 'phones':
                $table = 'contact-phones';
                break;
            case 'socials':
                $table = 'contact-socials';
                break;
            case 'address':
                $table = 'contact-address';
                break;
            default:
                echo json_encode(["success" => false, "error" => "Invalid resource"]);
                exit;
        }

        $sql = "UPDATE `$table` SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = $id";

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