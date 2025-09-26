<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createMarquee = "CREATE TABLE IF NOT EXISTS marquee (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        text TEXT NOT NULL,
        external_links TEXT,
        pdf VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createMarquee)) {
        return ["success" => false, "error" => "Failed to create marquee table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_marquee_date ON marquee(date)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_marquee_created ON marquee(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function marqueeTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'marquee'");
    return $result && $result->num_rows > 0;
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
            $result = $conn->query("SELECT * FROM marquee 
                                   WHERE text LIKE '%$keyword%' 
                                   OR external_links LIKE '%$keyword%' 
                                   ORDER BY date DESC, id DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No marquee entries found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['date'])) {
                $date = $conn->real_escape_string($_GET['date']);
                $whereClauses[] = "date = '$date'";
            }

            if (!empty($_GET['date_from'])) {
                $date_from = $conn->real_escape_string($_GET['date_from']);
                $whereClauses[] = "date >= '$date_from'";
            }

            if (!empty($_GET['date_to'])) {
                $date_to = $conn->real_escape_string($_GET['date_to']);
                $whereClauses[] = "date <= '$date_to'";
            }

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            $query = "SELECT * FROM marquee";
            
            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }
            $query .= " ORDER BY date DESC, id DESC";

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

        $requiredFields = ['date', 'text'];
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

        $date = $input['date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(["success" => false, "error" => "Invalid date format. Use YYYY-MM-DD"]);
            break;
        }

        $date = $conn->real_escape_string($date);
        $text = $conn->real_escape_string($input['text']);
        $external_links = isset($input['external_links']) ? $conn->real_escape_string($input['external_links']) : null;
        $pdf = isset($input['pdf']) ? $conn->real_escape_string($input['pdf']) : null;

        $sql = "INSERT INTO marquee (date, text, external_links, pdf) 
                VALUES ('$date', '$text', " . 
                ($external_links ? "'$external_links'" : "NULL") . ", " .
                ($pdf ? "'$pdf'" : "NULL") . ")";

        if ($conn->query($sql)) {
            $marquee_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "marquee_id" => $marquee_id,
                "date" => $date,
                "text" => $input['text']
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
        if (!empty($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $whereClauses[] = "date = '$date'";
        }
        if (!empty($_GET['date_from'])) {
            $date_from = $conn->real_escape_string($_GET['date_from']);
            $whereClauses[] = "date >= '$date_from'";
        }
        if (!empty($_GET['date_to'])) {
            $date_to = $conn->real_escape_string($_GET['date_to']);
            $whereClauses[] = "date <= '$date_to'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(text LIKE '%$keyword%' OR external_links LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/date/date_from/date_to/keyword required)"]);
            break;
        }

        $allowedFields = ['date', 'text', 'external_links', 'pdf'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    echo json_encode(["success" => false, "error" => "Invalid date format. Use YYYY-MM-DD"]);
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
        $sql = "UPDATE marquee SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $whereClauses[] = "date = '$date'";
        }
        if (!empty($_GET['date_from'])) {
            $date_from = $conn->real_escape_string($_GET['date_from']);
            $whereClauses[] = "date >= '$date_from'";
        }
        if (!empty($_GET['date_to'])) {
            $date_to = $conn->real_escape_string($_GET['date_to']);
            $whereClauses[] = "date <= '$date_to'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(text LIKE '%$keyword%' OR external_links LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/date/date_from/date_to/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM marquee WHERE " . implode(" AND ", $whereClauses);

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