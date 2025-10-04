<?php
include '../server.php';

header("Content-Type: application/json");

function createNirfTableIfNotExist($conn) {
    $createNirfReports = "CREATE TABLE IF NOT EXISTS `nirf_reports` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year INT NOT NULL UNIQUE,
        pdf_path VARCHAR(500) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createNirfReports)) {
        return ["success" => false, "error" => "Failed to create nirf_reports table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_nirf_reports_year ON `nirf_reports`(year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_nirf_reports_active ON `nirf_reports`(is_active)");

    return ["success" => true, "message" => "NIRF table created/verified successfully"];
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $initResult = createNirfTableIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $query = "SELECT year, pdf_path FROM nirf_reports WHERE is_active = TRUE ORDER BY year DESC";
        $result = $conn->query($query);
        
        if ($result) {
            $reports = [];
            while ($row = $result->fetch_assoc()) {
                $reports[] = $row;
            }
            echo json_encode($reports);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'POST':
        $initResult = createNirfTableIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (empty($input['year']) || empty($input['pdf_path'])) {
            echo json_encode(["success" => false, "error" => "Missing required fields: year and pdf_path"]);
            break;
        }

        $year = (int)$input['year'];
        $pdf_path = $conn->real_escape_string($input['pdf_path']);

        $checkDuplicate = $conn->query("SELECT id FROM nirf_reports WHERE year = $year");
        if ($checkDuplicate && $checkDuplicate->num_rows > 0) {
            echo json_encode(["success" => false, "error" => "A report for this year already exists"]);
            break;
        }

        $sql = "INSERT INTO nirf_reports (year, pdf_path) VALUES ($year, '$pdf_path')";

        if ($conn->query($sql)) {
            $record_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "id" => $record_id,
                "message" => "NIRF report added successfully"
            ]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'PATCH':
        $initResult = createNirfTableIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID parameter required for update"]);
            break;
        }

        $id = (int)$_GET['id'];
        $updates = [];

        if (!empty($input['year'])) {
            $year = (int)$input['year'];
            $updates[] = "year = $year";
        }

        if (!empty($input['pdf_path'])) {
            $pdf_path = $conn->real_escape_string($input['pdf_path']);
            $updates[] = "pdf_path = '$pdf_path'";
        }

        if (isset($input['is_active'])) {
            $is_active = (int)$input['is_active'];
            $updates[] = "is_active = $is_active";
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE nirf_reports SET " . implode(", ", $updates) . " WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "updated_rows" => $conn->affected_rows]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'DELETE':
        $initResult = createNirfTableIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "ID parameter required for deletion"]);
            break;
        }

        $id = (int)$_GET['id'];

        $sql = "UPDATE nirf_reports SET is_active = FALSE, updated_at = CURRENT_TIMESTAMP WHERE id = $id";

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