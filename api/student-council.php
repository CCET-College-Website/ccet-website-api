<?php
include '../server.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Create tables with role field and PDF table
function createTables($conn) {
    // Student Council Members table
    $conn->query("CREATE TABLE IF NOT EXISTS student_council (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role ENUM('incharge', 'member') NOT NULL DEFAULT 'member',
        position VARCHAR(100),
        name VARCHAR(255) NOT NULL,
        roll_no VARCHAR(50),
        year_semester VARCHAR(50),
        branch VARCHAR(255),
        designation VARCHAR(255),
        department VARCHAR(255),
        mobile_no VARCHAR(20),
        email VARCHAR(255),
        image VARCHAR(500),
        bio TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        academic_year VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Student Council PDFs table
    $conn->query("CREATE TABLE IF NOT EXISTS student_council_pdfs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        pdf_url VARCHAR(500) NOT NULL,
        category ENUM('faq', 'constitution', 'minutes', 'other') DEFAULT 'faq',
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        academic_year VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Create indexes for better performance
    $conn->query("CREATE INDEX IF NOT EXISTS idx_role ON student_council(role)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_active ON student_council(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_order ON student_council(display_order)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_year ON student_council(academic_year)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_pdf_category ON student_council_pdfs(category)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_pdf_active ON student_council_pdfs(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_pdf_year ON student_council_pdfs(academic_year)");
    
    // Insert default FAQ PDF if none exists
    $checkPdf = $conn->query("SELECT COUNT(*) as count FROM student_council_pdfs WHERE category = 'faq' AND is_active = 1");
    if ($checkPdf && $checkPdf->fetch_assoc()['count'] == 0) {
        $conn->query("INSERT INTO student_council_pdfs (title, description, pdf_url, category, display_order, academic_year) VALUES 
            ('Student Council FAQs', 'Frequently Asked Questions about Student Council', '/pdf/StudentCouncilFAQ.pdf', 'faq', 1, '2024-25')");
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Create tables on every request
createTables($conn);

switch ($method) {
    case 'GET':
        // Get PDFs
        if (isset($_GET['get_pdfs'])) {
            $category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : 'faq';
            $academic_year = isset($_GET['academic_year']) ? $conn->real_escape_string($_GET['academic_year']) : '';
            
            $query = "SELECT * FROM student_council_pdfs WHERE is_active = 1";
            if ($category) {
                $query .= " AND category = '$category'";
            }
            if ($academic_year) {
                $query .= " AND academic_year = '$academic_year'";
            }
            $query .= " ORDER BY display_order ASC, id ASC";
            
            $result = $conn->query($query);
            $pdfs = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'pdfs' => $pdfs]);
            break;
        }
        
        // Get available academic years
        if (isset($_GET['get_years'])) {
            $result = $conn->query("SELECT DISTINCT academic_year FROM student_council WHERE is_active = 1 AND academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC");
            $years = [];
            while ($row = $result->fetch_assoc()) {
                $years[] = $row['academic_year'];
            }
            echo json_encode(['success' => true, 'years' => $years]);
            break;
        }
        
        $academic_year = isset($_GET['academic_year']) ? $conn->real_escape_string($_GET['academic_year']) : date('Y') . '-' . (date('Y') + 1);
        
        // Get faculty incharge
        if (isset($_GET['type']) && $_GET['type'] === 'incharge') {
            $result = $conn->query("SELECT * FROM student_council WHERE role = 'incharge' AND is_active = 1 LIMIT 1");
            $data = $result->fetch_assoc();
            echo json_encode($data ?: []);
            break;
        }
        
        // Get council members
        if (isset($_GET['type']) && $_GET['type'] === 'members') {
            $result = $conn->query("SELECT * FROM student_council WHERE role = 'member' AND is_active = 1 AND academic_year = '$academic_year' ORDER BY display_order ASC, id ASC");
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($data);
            break;
        }
        
        // Get single record by ID
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $conn->query("SELECT * FROM student_council WHERE id = $id AND is_active = 1");
            $data = $result->fetch_assoc();
            echo json_encode($data ?: []);
            break;
        }
        
        // Get all data (both incharge and members)
        $inchargeResult = $conn->query("SELECT * FROM student_council WHERE role = 'incharge' AND is_active = 1 LIMIT 1");
        $membersResult = $conn->query("SELECT * FROM student_council WHERE role = 'member' AND is_active = 1 AND academic_year = '$academic_year' ORDER BY display_order ASC, id ASC");
        $pdfsResult = $conn->query("SELECT * FROM student_council_pdfs WHERE is_active = 1 AND category = 'faq' ORDER BY display_order ASC LIMIT 1");
        
        echo json_encode([
            'success' => true,
            'faculty_incharge' => $inchargeResult->fetch_assoc(),
            'council_members' => $membersResult->fetch_all(MYSQLI_ASSOC),
            'academic_year' => $academic_year,
            'faq_pdf' => $pdfsResult->fetch_assoc()
        ]);
        break;

    case 'POST':
        // Handle PDF upload/insert
        if (isset($_GET['pdf']) && $_GET['pdf'] == 1) {
            $required_fields = ['title', 'pdf_url'];
            $missing = [];
            
            foreach ($required_fields as $field) {
                if (empty($input[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                echo json_encode(['success' => false, 'error' => 'Missing fields: ' . implode(', ', $missing)]);
                break;
            }
            
            $title = $conn->real_escape_string($input['title']);
            $description = isset($input['description']) ? $conn->real_escape_string($input['description']) : null;
            $pdf_url = $conn->real_escape_string($input['pdf_url']);
            $category = isset($input['category']) ? $conn->real_escape_string($input['category']) : 'faq';
            $display_order = isset($input['display_order']) ? intval($input['display_order']) : 0;
            $academic_year = isset($input['academic_year']) ? $conn->real_escape_string($input['academic_year']) : date('Y') . '-' . (date('Y') + 1);
            
            $sql = "INSERT INTO student_council_pdfs (title, description, pdf_url, category, display_order, academic_year) 
                    VALUES ('$title', " . ($description ? "'$description'" : "NULL") . ", '$pdf_url', '$category', $display_order, '$academic_year')";
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'PDF added successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            break;
        }
        
        // Add new record (incharge or member)
        $required_fields = ['role', 'name'];
        $missing = [];
        
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            echo json_encode(['success' => false, 'error' => 'Missing fields: ' . implode(', ', $missing)]);
            break;
        }
        
        $role = $conn->real_escape_string($input['role']);
        $name = $conn->real_escape_string($input['name']);
        $position = isset($input['position']) ? $conn->real_escape_string($input['position']) : null;
        $roll_no = isset($input['roll_no']) ? $conn->real_escape_string($input['roll_no']) : null;
        $year_semester = isset($input['year_semester']) ? $conn->real_escape_string($input['year_semester']) : null;
        $branch = isset($input['branch']) ? $conn->real_escape_string($input['branch']) : null;
        $designation = isset($input['designation']) ? $conn->real_escape_string($input['designation']) : null;
        $department = isset($input['department']) ? $conn->real_escape_string($input['department']) : null;
        $mobile_no = isset($input['mobile_no']) ? $conn->real_escape_string($input['mobile_no']) : null;
        $email = isset($input['email']) ? $conn->real_escape_string($input['email']) : null;
        $image = isset($input['image']) ? $conn->real_escape_string($input['image']) : null;
        $bio = isset($input['bio']) ? $conn->real_escape_string($input['bio']) : null;
        $display_order = isset($input['display_order']) ? intval($input['display_order']) : 0;
        $academic_year = isset($input['academic_year']) ? $conn->real_escape_string($input['academic_year']) : date('Y') . '-' . (date('Y') + 1);
        
        $sql = "INSERT INTO student_council (role, position, name, roll_no, year_semester, branch, designation, department, mobile_no, email, image, bio, display_order, academic_year) 
                VALUES ('$role', " . ($position ? "'$position'" : "NULL") . ", '$name', " . ($roll_no ? "'$roll_no'" : "NULL") . ", " . ($year_semester ? "'$year_semester'" : "NULL") . ", " . ($branch ? "'$branch'" : "NULL") . ", " . ($designation ? "'$designation'" : "NULL") . ", " . ($department ? "'$department'" : "NULL") . ", " . ($mobile_no ? "'$mobile_no'" : "NULL") . ", " . ($email ? "'$email'" : "NULL") . ", " . ($image ? "'$image'" : "NULL") . ", " . ($bio ? "'$bio'" : "NULL") . ", $display_order, '$academic_year')";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id, 'message' => 'Record created successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;

    case 'PUT':
        // Update PDF
        if (isset($_GET['pdf']) && $_GET['pdf'] == 1) {
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID required']);
                break;
            }
            
            $id = intval($_GET['id']);
            $updates = [];
            $allowed_fields = ['title', 'description', 'pdf_url', 'category', 'display_order', 'is_active', 'academic_year'];
            
            foreach ($input as $key => $value) {
                if (in_array($key, $allowed_fields)) {
                    if ($value === null || $value === '') {
                        $updates[] = "$key = NULL";
                    } else {
                        $value = $conn->real_escape_string($value);
                        $updates[] = "$key = '$value'";
                    }
                }
            }
            
            if (empty($updates)) {
                echo json_encode(['success' => false, 'error' => 'No fields to update']);
                break;
            }
            
            $sql = "UPDATE student_council_pdfs SET " . implode(", ", $updates) . " WHERE id = $id";
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'PDF updated successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            break;
        }
        
        // Update record
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID required']);
            break;
        }
        
        $id = intval($_GET['id']);
        $updates = [];
        $allowed_fields = ['role', 'position', 'name', 'roll_no', 'year_semester', 'branch', 'designation', 'department', 'mobile_no', 'email', 'image', 'bio', 'display_order', 'is_active', 'academic_year'];
        
        foreach ($input as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                if ($value === null || $value === '') {
                    $updates[] = "$key = NULL";
                } else {
                    $value = $conn->real_escape_string($value);
                    $updates[] = "$key = '$value'";
                }
            }
        }
        
        if (empty($updates)) {
            echo json_encode(['success' => false, 'error' => 'No fields to update']);
            break;
        }
        
        $sql = "UPDATE student_council SET " . implode(", ", $updates) . " WHERE id = $id";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;

    case 'DELETE':
        // Delete PDF (soft delete)
        if (isset($_GET['pdf']) && $_GET['pdf'] == 1) {
            if (!isset($_GET['id'])) {
                echo json_encode(['success' => false, 'error' => 'ID required']);
                break;
            }
            
            $id = intval($_GET['id']);
            $sql = "UPDATE student_council_pdfs SET is_active = 0 WHERE id = $id";
            
            if ($conn->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'PDF deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
            break;
        }
        
        // Soft delete (set is_active = 0)
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID required']);
            break;
        }
        
        $id = intval($_GET['id']);
        $sql = "UPDATE student_council SET is_active = 0 WHERE id = $id";
        
        if ($conn->query($sql)) {
            echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        break;
}

$conn->close();
?>