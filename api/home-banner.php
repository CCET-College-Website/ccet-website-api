<?php
include '../server.php';

header("Content-Type: application/json");

function createTablesIfNotExist($conn) {
    /*
     * Two separate image columns:
     *   banner_image_desktop  – landscape crop shown on screens ≥ 768 px  (required)
     *   banner_image_mobile   – portrait/square crop shown on screens < 768 px (optional)
     *
     * If banner_image_mobile is NULL the frontend falls back to the desktop image.
     */
    $createBanner = "CREATE TABLE IF NOT EXISTS `home_banner` (
        id                    INT AUTO_INCREMENT PRIMARY KEY,
        title                 VARCHAR(255)         DEFAULT NULL,
        subtitle              VARCHAR(500)         DEFAULT NULL,
        banner_image_desktop  VARCHAR(255)         NOT NULL,
        banner_image_mobile   VARCHAR(255)         DEFAULT NULL,
        link_url              VARCHAR(500)         DEFAULT NULL,
        sort_order            INT                  NOT NULL DEFAULT 0,
        is_active             TINYINT(1)           NOT NULL DEFAULT 1,
        created_at            TIMESTAMP            DEFAULT CURRENT_TIMESTAMP,
        updated_at            TIMESTAMP            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createBanner)) {
        return ["success" => false, "error" => "Failed to create home_banner table: " . $conn->error];
    }

    $conn->query("CREATE INDEX IF NOT EXISTS idx_banner_active ON `home_banner`(is_active)");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_banner_order  ON `home_banner`(sort_order)");

    return ["success" => true, "message" => "Tables created/verified successfully"];
}

/*
 * Migration helper — call once if you already have an existing table that uses
 * the old single `banner_image` column. Safe to run multiple times (IF NOT EXISTS).
 *
 * Uncomment the migration block below, hit the endpoint once, then re-comment it.
 */
// function migrateOldColumn($conn) {
//     $conn->query("ALTER TABLE `home_banner`
//         ADD COLUMN IF NOT EXISTS `banner_image_desktop` VARCHAR(255) NULL AFTER `subtitle`,
//         ADD COLUMN IF NOT EXISTS `banner_image_mobile`  VARCHAR(255) NULL AFTER `banner_image_desktop`");
//     // Copy the old column into desktop and mark old column as deprecated
//     $conn->query("UPDATE `home_banner` SET `banner_image_desktop` = `banner_image` WHERE `banner_image_desktop` IS NULL");
//     // Optionally drop the old column after verifying data is intact:
//     // $conn->query("ALTER TABLE `home_banner` DROP COLUMN `banner_image`");
// }

function isValidImageFile($file_path) {
    $valid_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    return in_array($file_extension, $valid_extensions);
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true);

switch ($method) {

    // ──────────────────────────────────────────────────────────────────────────
    case 'GET':
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

        if (isset($_GET['is_active'])) {
            $whereClauses[] = "is_active = " . ((int)$_GET['is_active'] ? 1 : 0);
        } else {
            $whereClauses[] = "is_active = 1";
        }

        $query = "SELECT * FROM `home_banner`";
        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $query .= " ORDER BY sort_order ASC, id ASC";

        $result = $conn->query($query);
        if ($result) {
            $data = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($data); // raw paths, same as gallery.php
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    // ──────────────────────────────────────────────────────────────────────────
    case 'POST':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        // Desktop image is required
        if (empty($input['banner_image_desktop'])) {
            echo json_encode(["success" => false, "error" => "Missing required field: banner_image_desktop"]);
            break;
        }

        if (!isValidImageFile($input['banner_image_desktop'])) {
            echo json_encode(["success" => false, "error" => "Invalid desktop image file type. Use: jpg, jpeg, png, gif, bmp, webp, svg"]);
            break;
        }

        // Mobile image is optional; validate only if provided
        if (!empty($input['banner_image_mobile']) && !isValidImageFile($input['banner_image_mobile'])) {
            echo json_encode(["success" => false, "error" => "Invalid mobile image file type. Use: jpg, jpeg, png, gif, bmp, webp, svg"]);
            break;
        }

        $banner_image_desktop = $conn->real_escape_string($input['banner_image_desktop']);
        $banner_image_mobile  = !empty($input['banner_image_mobile'])
            ? "'" . $conn->real_escape_string($input['banner_image_mobile']) . "'"
            : "NULL";
        $title      = $conn->real_escape_string($input['title']    ?? '');
        $subtitle   = $conn->real_escape_string($input['subtitle'] ?? '');
        $link_url   = $conn->real_escape_string($input['link_url'] ?? '');
        $sort_order = (int)($input['sort_order'] ?? 0);
        $is_active  = isset($input['is_active']) ? ((int)$input['is_active'] ? 1 : 0) : 1;

        $sql = "INSERT INTO `home_banner`
                    (banner_image_desktop, banner_image_mobile, title, subtitle, link_url, sort_order, is_active)
                VALUES
                    ('$banner_image_desktop', $banner_image_mobile, '$title', '$subtitle', '$link_url', $sort_order, $is_active)";

        if ($conn->query($sql)) {
            echo json_encode([
                "success"              => true,
                "id"                   => $conn->insert_id,
                "banner_image_desktop" => $input['banner_image_desktop'],
                "banner_image_mobile"  => $input['banner_image_mobile'] ?? null,
                "title"                => $input['title']    ?? null,
                "subtitle"             => $input['subtitle'] ?? null,
                "link_url"             => $input['link_url'] ?? null,
                "sort_order"           => $sort_order,
                "is_active"            => $is_active,
            ]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    // ──────────────────────────────────────────────────────────────────────────
    case 'PATCH':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        $whereClauses = [];

        if (!empty($_GET['id'])) {
            $whereClauses[] = "id = " . (int)$_GET['id'];
        }
        if (isset($_GET['is_active'])) {
            $whereClauses[] = "is_active = " . ((int)$_GET['is_active'] ? 1 : 0);
        }

        if (empty($whereClauses)) {
            echo json_encode(["success" => false, "error" => "No filter provided (id required)"]);
            break;
        }

        $allowedFields = ['banner_image_desktop', 'banner_image_mobile', 'title', 'subtitle', 'link_url', 'sort_order', 'is_active'];
        $updates = [];

        foreach ($input as $key => $value) {
            if (!in_array($key, $allowedFields)) continue;

            if (in_array($key, ['banner_image_desktop', 'banner_image_mobile']) && !empty($value)) {
                if (!isValidImageFile($value)) {
                    $label = $key === 'banner_image_desktop' ? 'desktop' : 'mobile';
                    echo json_encode(["success" => false, "error" => "Invalid $label image file type. Use: jpg, jpeg, png, gif, bmp, webp, svg"]);
                    break 2;
                }
            }

            if ($key === 'sort_order') {
                $updates[] = "sort_order = " . (int)$value;
            } elseif ($key === 'is_active') {
                $updates[] = "is_active = " . ((int)$value ? 1 : 0);
            } elseif ($key === 'banner_image_mobile' && ($value === null || $value === '')) {
                // Allow explicitly clearing the mobile image
                $updates[] = "banner_image_mobile = NULL";
            } else {
                $safe = $conn->real_escape_string($value);
                $updates[] = "$key = '$safe'";
            }
        }

        if (empty($updates)) {
            echo json_encode(["success" => false, "error" => "No valid fields to update"]);
            break;
        }

        $updates[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE `home_banner` SET " . implode(", ", $updates) . " WHERE " . implode(" AND ", $whereClauses);

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "updated_rows" => $conn->affected_rows]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    // ──────────────────────────────────────────────────────────────────────────
    case 'DELETE':
        $initResult = createTablesIfNotExist($conn);
        if (!$initResult["success"]) {
            echo json_encode($initResult);
            break;
        }

        if (empty($_GET['id'])) {
            echo json_encode(["success" => false, "error" => "No filter provided (id required)"]);
            break;
        }

        $id  = (int)$_GET['id'];
        $sql = "DELETE FROM `home_banner` WHERE id = $id";

        if ($conn->query($sql)) {
            echo json_encode(["success" => true, "deleted_rows" => $conn->affected_rows]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    // ──────────────────────────────────────────────────────────────────────────
    default:
        echo json_encode(["success" => false, "error" => "Invalid request method"]);
        break;
}

$conn->close();
?>