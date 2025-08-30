<?php
include '../server.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $result = $conn->query("SELECT * FROM `faculty-mech` WHERE id = $id");
            $data = $result->fetch_assoc();
        } else {
            $result = $conn->query("SELECT * FROM `faculty-mech`");
            $data = $result->fetch_all(MYSQLI_ASSOC);
        }
        echo json_encode($data);
        break;

    case 'POST':
        $name = $conn->real_escape_string($input['name']);
        $img = $conn->real_escape_string($input['img']);
        $department = $conn->real_escape_string($input['department']);
        $designation = $conn->real_escape_string($input['designation']);
        $edu = $conn->real_escape_string($input['edu']);
        $add_role = $conn->real_escape_string($input['add_role']);
        $interest = $conn->real_escape_string($input['interest']);
        $number = $conn->real_escape_string($input['number']);
        $email = $conn->real_escape_string($input['email']);
        $address = $conn->real_escape_string($input['address']);
        $resume_link = isset($input['resume_link']) ? $conn->real_escape_string($input['resume_link']) : NULL;

        $sql = "INSERT INTO `faculty-mech` 
                (name, img, department, designation, edu, add_role, interest, number, email, address, resume_link)
                VALUES ('$name','$img','$department','$designation','$edu','$add_role','$interest',
                        '$number','$email','$address',".($resume_link ? "'$resume_link'" : "NULL").")";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "id" => $conn->insert_id]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'PATCH':
        if (!isset($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "No ID provided"]);
            break;
        }
        $id = intval($_GET['id']);
        $updates = [];
        foreach ($input as $key => $value) {
            $value = $conn->real_escape_string($value);
            $updates[] = "$key='$value'";
        }
        $sql = "UPDATE `faculty-mech` SET ".implode(", ", $updates)." WHERE id=$id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "No ID provided"]);
            break;
        }
        $id = intval($_GET['id']);
        $sql = "DELETE FROM `faculty-mech` WHERE id=$id";
        if ($conn->query($sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    default:
        echo json_encode(["success" => false, "error" => "Invalid request"]);
        break;
}

$conn->close();
?>
