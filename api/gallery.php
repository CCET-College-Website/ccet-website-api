<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    $createGallery = "CREATE TABLE IF NOT EXISTS `gallery` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_type VARCHAR(100) NOT NULL,
        date DATE NOT NULL,
        uploaded_image VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($createGallery)) {
        return ["success" => false, "error" => "Failed to create gallery table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_image_type ON `gallery`(image_type)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_date ON `gallery`(date)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_type_date ON `gallery`(image_type, date)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_gallery_created ON `gallery`(created_at)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

function galleryTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'gallery'");
    return $result && $result->num_rows > 0;
}

function isValidImageType($image_type) {
    $validTypes = [
        'events', 'fest', 'festival', 'ceremony', 'graduation', 'convocation',
        'workshop', 'seminar', 'conference', 'competition', 'sports', 'cultural',
        'technical', 'academic', 'infrastructure', 'campus', 'laboratory', 'library',
        'hostel', 'canteen', 'auditorium', 'classroom', 'faculty', 'students',
        'achievements', 'awards', 'celebrations', 'meetings', 'orientation',
        'farewell', 'fresher', 'alumni', 'placements', 'internship', 'research',
        'projects', 'exhibitions', 'hackathon', 'coding', 'robotics', 'science',
        'arts', 'dance', 'music', 'drama', 'debate', 'quiz', 'other'
    ];
    
    return in_array(strtolower($image_type), $validTypes);
}

function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function isValidImageFile($file_path) {
    $valid_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($file_extension, $valid_extensions);
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
            $result = $conn->query("SELECT * FROM `gallery` 
                                   WHERE image_type LIKE '%$keyword%' 
                                   OR uploaded_image LIKE '%$keyword%'
                                   OR date LIKE '%$keyword%'
                                   ORDER BY date DESC, id DESC");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_all(MYSQLI_ASSOC);
                echo json_encode($data);
            } else {
                echo json_encode(["success" => false, "error" => "No gallery images found with that keyword"]);
            }
        } else {
            $whereClauses = [];

            if (!empty($_GET['id'])) {
                $id = (int)$_GET['id'];
                $whereClauses[] = "id = $id";
            }

            if (!empty($_GET['image_type'])) {
                $image_type = $conn->real_escape_string($_GET['image_type']);
                $whereClauses[] = "image_type = '$image_type'";
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

            if (!empty($_GET['year'])) {
                $year = (int)$_GET['year'];
                $whereClauses[] = "YEAR(date) = $year";
            }

            if (!empty($_GET['month'])) {
                $month = (int)$_GET['month'];
                $whereClauses[] = "MONTH(date) = $month";
            }

            $query = "SELECT * FROM `gallery`";
            
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

        $requiredFields = ['image_type', 'date', 'uploaded_image'];
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

        if (!isValidImageType($input['image_type'])) {
            echo json_encode(["success" => false, "error" => "Invalid image type. Use types like: events, fest, ceremony, workshop, sports, cultural, technical, campus, etc."]);
            break;
        }

        if (!isValidDate($input['date'])) {
            echo json_encode(["success" => false, "error" => "Invalid date format. Use YYYY-MM-DD format"]);
            break;
        }

        if (!isValidImageFile($input['uploaded_image'])) {
            echo json_encode(["success" => false, "error" => "Invalid image file type. Use: jpg, jpeg, png, gif, bmp, webp, svg"]);
            break;
        }

        $image_type = $conn->real_escape_string(strtolower($input['image_type']));
        $date = $conn->real_escape_string($input['date']);
        $uploaded_image = $conn->real_escape_string($input['uploaded_image']);

        $sql = "INSERT INTO `gallery` (image_type, date, uploaded_image) 
                VALUES ('$image_type', '$date', '$uploaded_image')";

        if ($conn->query($sql)) {
            $gallery_id = $conn->insert_id;
            echo json_encode([
                "success" => true, 
                "gallery_id" => $gallery_id,
                "image_type" => $input['image_type'],
                "date" => $input['date'],
                "uploaded_image" => $input['uploaded_image']
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
        if (!empty($_GET['image_type'])) {
            $image_type = $conn->real_escape_string($_GET['image_type']);
            $whereClauses[] = "image_type = '$image_type'";
        }
        if (!empty($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $whereClauses[] = "date = '$date'";
        }
        if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
            $date_from = $conn->real_escape_string($_GET['date_from']);
            $date_to = $conn->real_escape_string($_GET['date_to']);
            $whereClauses[] = "date BETWEEN '$date_from' AND '$date_to'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(image_type LIKE '%$keyword%' OR uploaded_image LIKE '%$keyword%' OR date LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/image_type/date/date_from&date_to/keyword required)"]);
            break;
        }

        $allowedFields = ['image_type', 'date', 'uploaded_image'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $allowedFields)) {
                if ($key === 'image_type' && !empty($value) && !isValidImageType($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid image type. Use types like: events, fest, ceremony, workshop, sports, cultural, technical, campus, etc."]);
                    break 2;
                }
                
                if ($key === 'date' && !empty($value) && !isValidDate($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid date format. Use YYYY-MM-DD format"]);
                    break 2;
                }
                
                if ($key === 'uploaded_image' && !empty($value) && !isValidImageFile($value)) {
                    echo json_encode(["success" => false, "error" => "Invalid image file type. Use: jpg, jpeg, png, gif, bmp, webp, svg"]);
                    break 2;
                }
                
                if ($value === null || $value === '') {
                    echo json_encode(["success" => false, "error" => "All fields (image_type, date, uploaded_image) are required and cannot be empty"]);
                    break 2;
                } else {
                    if ($key === 'image_type') {
                        $value = strtolower($value);
                    }
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
        $sql = "UPDATE `gallery` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

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
        if (!empty($_GET['image_type'])) {
            $image_type = $conn->real_escape_string($_GET['image_type']);
            $whereClauses[] = "image_type = '$image_type'";
        }
        if (!empty($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $whereClauses[] = "date = '$date'";
        }
        if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
            $date_from = $conn->real_escape_string($_GET['date_from']);
            $date_to = $conn->real_escape_string($_GET['date_to']);
            $whereClauses[] = "date BETWEEN '$date_from' AND '$date_to'";
        }
        if (!empty($_GET['keyword'])) {
            $keyword = $conn->real_escape_string($_GET['keyword']);
            $whereClauses[] = "(image_type LIKE '%$keyword%' OR uploaded_image LIKE '%$keyword%' OR date LIKE '%$keyword%')";
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id/image_type/date/date_from&date_to/keyword required)"]);
            break;
        }

        $sql = "DELETE FROM `gallery` WHERE " . implode(" AND ", $whereClauses);

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