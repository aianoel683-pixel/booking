<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin','manager'])) { header('Location: ../auth/login.php'); exit(); }

$page_title = 'Settings';
$db = db();

// Ensure system_settings has hotel_logo
try {
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_settings' AND COLUMN_NAME = 'hotel_logo'");
    $stmt->execute();
    if ((int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0) === 0) {
        $db->exec("ALTER TABLE system_settings ADD COLUMN hotel_logo TEXT");
    }
} catch (Exception $e) {}

function handle_image_upload($field, $prevPath = '', $subDir = 'landing') {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return $prevPath;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return $prevPath;
    $tmp = $_FILES[$field]['tmp_name'] ?? '';
    $size = (int)($_FILES[$field]['size'] ?? 0);
    $type = $_FILES[$field]['type'] ?? '';
    if (!$tmp || $size <= 0) return $prevPath;
    if ($size > 10 * 1024 * 1024) return $prevPath;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$type])) return $prevPath;
    $ext = $allowed[$type];
    $baseDir = __DIR__ . '/../uploads/' . $subDir;
    if (!is_dir($baseDir)) { @mkdir(__DIR__ . '/../uploads', 0755, true); @mkdir($baseDir, 0755, true); }
    $name = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destFs = $baseDir . '/' . $name;
    if (@move_uploaded_file($tmp, $destFs)) {
        return 'uploads/' . $subDir . '/' . $name;
    }
    return $prevPath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $hotel_name = trim($_POST['hotel_name'] ?? '');
    $hotel_address = trim($_POST['hotel_address'] ?? '');
    $hotel_phone = trim($_POST['hotel_phone'] ?? '');
    $hotel_email = trim($_POST['hotel_email'] ?? '');
    $tax_rate = (float)($_POST['tax_rate'] ?? 0);
    $check_in_time = $_POST['check_in_time'] ?? '14:00:00';
    $check_out_time = $_POST['check_out_time'] ?? '12:00:00';
    $currency = trim($_POST['currency'] ?? 'PHP');

    $current_settings = $db->query("SELECT * FROM system_settings ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $hotel_logo = handle_image_upload('hotel_logo', $current_settings['hotel_logo'] ?? '', 'system');

    $exists = $db->query("SELECT COUNT(*) AS c FROM system_settings")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;

    if ((int)$exists > 0) {
        $stmt = $db->prepare("UPDATE system_settings SET hotel_name=:hn, hotel_address=:ha, hotel_phone=:hp, hotel_email=:he, tax_rate=:tr, check_in_time=:cit, check_out_time=:cot, currency=:cur, hotel_logo=:hl, updated_at=NOW() WHERE id=(SELECT id FROM system_settings ORDER BY id LIMIT 1)");
        $stmt->execute([':hn'=>$hotel_name, ':ha'=>$hotel_address, ':hp'=>$hotel_phone, ':he'=>$hotel_email, ':tr'=>$tax_rate, ':cit'=>$check_in_time, ':cot'=>$check_out_time, ':cur'=>$currency, ':hl'=>$hotel_logo]);
    } else {
        $stmt = $db->prepare("INSERT INTO system_settings (hotel_name, hotel_address, hotel_phone, hotel_email, tax_rate, check_in_time, check_out_time, currency, hotel_logo) VALUES (:hn,:ha,:hp,:he,:tr,:cit,:cot,:cur,:hl)");
        $stmt->execute([':hn'=>$hotel_name, ':ha'=>$hotel_address, ':hp'=>$hotel_phone, ':he'=>$hotel_email, ':tr'=>$tax_rate, ':cit'=>$check_in_time, ':cot'=>$check_out_time, ':cur'=>$currency, ':hl'=>$hotel_logo]);
    }
    $success = 'Settings saved.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($current_password, $user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = :p WHERE id = :id");
            $stmt->execute([':p' => $hashed, ':id' => $_SESSION['user_id']]);
            $success = "Password changed successfully.";
        } else {
            $error = "Incorrect current password.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup_database') {
    $tables = [];
    $result = $db->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlScript = "-- Database Backup\n";
    $sqlScript .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Structure
        $row = $db->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
        $sqlScript .= "\n\n" . $row[1] . ";\n\n";

        // Data
        $result = $db->query("SELECT * FROM $table");
        $columnCount = $result->columnCount();

        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $sqlScript .= "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                if (!isset($row[$j])) {
                    $sqlScript .= "NULL";
                } else {
                    $sqlScript .= '"' . addslashes($row[$j]) . '"';
                }
                if ($j < ($columnCount - 1)) {
                    $sqlScript .= ',';
                }
            }
            $sqlScript .= ");\n";
        }
    }
    
    $sqlScript .= "\nSET FOREIGN_KEY_CHECKS=1;";

    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Clear any previous output
    if (ob_get_level()) ob_end_clean();
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $sqlScript;
    exit();
}

// Team members storage
try {
    $db->exec("CREATE TABLE IF NOT EXISTS team_members (\n        id INT PRIMARY KEY AUTO_INCREMENT,\n        name VARCHAR(255) NOT NULL,\n        position VARCHAR(255) DEFAULT '',\n        bio TEXT,\n        email VARCHAR(255) DEFAULT '',\n        phone VARCHAR(255) DEFAULT '',\n        initials VARCHAR(8) DEFAULT '',\n        visible TINYINT(1) DEFAULT 1,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_team_member') {
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $initials = trim($_POST['initials'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    if ($name !== '') {
        $stmt = $db->prepare("INSERT INTO team_members (name, position, bio, email, phone, initials, visible) VALUES (:n,:p,:b,:e,:ph,:i,1)");
        $stmt->execute([':n'=>$name, ':p'=>$position, ':b'=>$bio, ':e'=>$email, ':ph'=>$phone, ':i'=>$initials]);
        $success = 'Team member added.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_team_member') {
    $id = (int)($_POST['member_id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM team_members WHERE id=:id");
        $stmt->execute([':id'=>$id]);
        $success = 'Team member deleted.';
    }
}

    $db->exec("CREATE TABLE IF NOT EXISTS landing_content (
        id INT PRIMARY KEY AUTO_INCREMENT,
        hero_title VARCHAR(255) DEFAULT '',
        hero_subtitle VARCHAR(255) DEFAULT '',
        hero_bg_url TEXT,
        hero_button_text VARCHAR(64) DEFAULT 'Book Now!',
        hero_promo_text VARCHAR(255) DEFAULT '',
        hex_img_1 TEXT,
        hex_img_2 TEXT,
        hex_img_3 TEXT,
        booking_title VARCHAR(255) DEFAULT '',
        booking_subtitle VARCHAR(255) DEFAULT '',
        offer_weekend_text VARCHAR(255) DEFAULT '',
        offer_early_text VARCHAR(255) DEFAULT '',
        gallery_room_url TEXT,
        gallery_pool_url TEXT,
        gallery_dining_url TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure additional editable content fields exist
try {
    $columns = [
        ['welcome_label', "VARCHAR(255) DEFAULT ''"],
        ['welcome_title', "VARCHAR(255) DEFAULT ''"],
        ['welcome_subtitle', "VARCHAR(255) DEFAULT ''"],
        ['welcome_paragraph', "TEXT"],
        ['ceo_name', "VARCHAR(255) DEFAULT ''"],
        ['ceo_title', "VARCHAR(255) DEFAULT ''"],
        ['showcase_label', "VARCHAR(255) DEFAULT ''"],
        ['showcase_title', "VARCHAR(255) DEFAULT ''"],
        ['showcase_subtitle', "VARCHAR(255) DEFAULT ''"],
        ['showcase_paragraph', "TEXT"],
        ['footer_text', "VARCHAR(255) DEFAULT ''"],
        ['rooms_label', "VARCHAR(255) DEFAULT ''"],
        ['rooms_title', "VARCHAR(255) DEFAULT ''"],
        ['room_img_1', "TEXT"],
        ['room_img_2', "TEXT"],
        ['room_img_3', "TEXT"],
        ['room_title_1', "VARCHAR(255) DEFAULT ''"],
        ['room_title_2', "VARCHAR(255) DEFAULT ''"],
        ['room_title_3', "VARCHAR(255) DEFAULT ''"],
        ['team_label', "VARCHAR(255) DEFAULT ''"],
        ['team_title', "VARCHAR(255) DEFAULT ''"],
        ['team_description', "VARCHAR(255) DEFAULT ''"],
        ['contact_label', "VARCHAR(255) DEFAULT ''"],
        ['contact_title', "VARCHAR(255) DEFAULT ''"],
        ['team1_name', "VARCHAR(255) DEFAULT ''"],
        ['team1_position', "VARCHAR(255) DEFAULT ''"],
        ['team1_bio', "TEXT"],
        ['team1_email', "VARCHAR(255) DEFAULT ''"],
        ['team1_phone', "VARCHAR(255) DEFAULT ''"],
        ['team1_initials', "VARCHAR(8) DEFAULT ''"],
        ['team2_name', "VARCHAR(255) DEFAULT ''"],
        ['team2_position', "VARCHAR(255) DEFAULT ''"],
        ['team2_bio', "TEXT"],
        ['team2_email', "VARCHAR(255) DEFAULT ''"],
        ['team2_phone', "VARCHAR(255) DEFAULT ''"],
        ['team2_initials', "VARCHAR(8) DEFAULT ''"],
        ['team3_name', "VARCHAR(255) DEFAULT ''"],
        ['team3_position', "VARCHAR(255) DEFAULT ''"],
        ['team3_bio', "TEXT"],
        ['team3_email', "VARCHAR(255) DEFAULT ''"],
        ['team3_phone', "VARCHAR(255) DEFAULT ''"],
        ['team3_initials', "VARCHAR(8) DEFAULT ''"],
        ['team4_name', "VARCHAR(255) DEFAULT ''"],
        ['team4_position', "VARCHAR(255) DEFAULT ''"],
        ['team4_bio', "TEXT"],
        ['team4_email', "VARCHAR(255) DEFAULT ''"],
        ['team4_phone', "VARCHAR(255) DEFAULT ''"],
        ['team4_initials', "VARCHAR(8) DEFAULT ''"],
        ['team5_name', "VARCHAR(255) DEFAULT ''"],
        ['team5_position', "VARCHAR(255) DEFAULT ''"],
        ['team5_bio', "TEXT"],
        ['team5_email', "VARCHAR(255) DEFAULT ''"],
        ['team5_phone', "VARCHAR(255) DEFAULT ''"],
        ['team5_initials', "VARCHAR(8) DEFAULT ''"],
        ['team6_name', "VARCHAR(255) DEFAULT ''"],
        ['team6_position', "VARCHAR(255) DEFAULT ''"],
        ['team6_bio', "TEXT"],
        ['team6_email', "VARCHAR(255) DEFAULT ''"],
        ['team6_phone', "VARCHAR(255) DEFAULT ''"],
        ['team6_initials', "VARCHAR(8) DEFAULT ''"],
        ['team7_name', "VARCHAR(255) DEFAULT ''"],
        ['team7_position', "VARCHAR(255) DEFAULT ''"],
        ['team7_bio', "TEXT"],
        ['team7_email', "VARCHAR(255) DEFAULT ''"],
        ['team7_phone', "VARCHAR(255) DEFAULT ''"],
        ['team7_initials', "VARCHAR(8) DEFAULT ''"],
        ['team8_name', "VARCHAR(255) DEFAULT ''"],
        ['team8_position', "VARCHAR(255) DEFAULT ''"],
        ['team8_bio', "TEXT"],
        ['team8_email', "VARCHAR(255) DEFAULT ''"],
        ['team8_phone', "VARCHAR(255) DEFAULT ''"],
        ['team8_initials', "VARCHAR(8) DEFAULT ''"]
    ];
    foreach ($columns as $col) {
        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'landing_content' AND COLUMN_NAME = :col");
        $stmt->execute([':col' => $col[0]]);
        $exists = (int)($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        if ($exists === 0) {
            $db->exec("ALTER TABLE landing_content ADD COLUMN `{$col[0]}` {$col[1]}");
        }
    }
} catch (Exception $e) {}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_landing_content') {
    $current = null;
    try { $current = $db->query("SELECT * FROM landing_content ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}
    $hero_title = trim($_POST['hero_title'] ?? '');
    $hero_subtitle = trim($_POST['hero_subtitle'] ?? '');
    $hero_bg_url = handle_image_upload('hero_bg_url', $current['hero_bg_url'] ?? '');
    $hero_button_text = trim($_POST['hero_button_text'] ?? 'Book Now!');
    $hero_promo_text = trim($_POST['hero_promo_text'] ?? '');
    $hex_img_1 = handle_image_upload('hex_img_1', $current['hex_img_1'] ?? '');
    $hex_img_2 = handle_image_upload('hex_img_2', $current['hex_img_2'] ?? '');
    $hex_img_3 = handle_image_upload('hex_img_3', $current['hex_img_3'] ?? '');
    $booking_title = trim($_POST['booking_title'] ?? '');
    $booking_subtitle = trim($_POST['booking_subtitle'] ?? '');
    $offer_weekend_text = trim($_POST['offer_weekend_text'] ?? '');
    $offer_early_text = trim($_POST['offer_early_text'] ?? '');
    $gallery_room_url = handle_image_upload('gallery_room_url', $current['gallery_room_url'] ?? '');
    $gallery_pool_url = handle_image_upload('gallery_pool_url', $current['gallery_pool_url'] ?? '');
    $gallery_dining_url = handle_image_upload('gallery_dining_url', $current['gallery_dining_url'] ?? '');
    $welcome_label = trim($_POST['welcome_label'] ?? '');
    $welcome_title = trim($_POST['welcome_title'] ?? '');
    $welcome_subtitle = trim($_POST['welcome_subtitle'] ?? '');
    $welcome_paragraph = trim($_POST['welcome_paragraph'] ?? '');
    $ceo_name = trim($_POST['ceo_name'] ?? '');
    $ceo_title = trim($_POST['ceo_title'] ?? '');
    $showcase_label = trim($_POST['showcase_label'] ?? '');
    $showcase_title = trim($_POST['showcase_title'] ?? '');
    $showcase_subtitle = trim($_POST['showcase_subtitle'] ?? '');
    $showcase_paragraph = trim($_POST['showcase_paragraph'] ?? '');
    $footer_text = trim($_POST['footer_text'] ?? '');
    $rooms_label = trim($_POST['rooms_label'] ?? '');
    $rooms_title = trim($_POST['rooms_title'] ?? '');
    $room_img_1 = handle_image_upload('room_img_1', $current['room_img_1'] ?? '');
    $room_img_2 = handle_image_upload('room_img_2', $current['room_img_2'] ?? '');
    $room_img_3 = handle_image_upload('room_img_3', $current['room_img_3'] ?? '');
    $room_title_1 = trim($_POST['room_title_1'] ?? '');
    $room_title_2 = trim($_POST['room_title_2'] ?? '');
    $room_title_3 = trim($_POST['room_title_3'] ?? '');
    $team_label = trim($_POST['team_label'] ?? '');
    $team_title = trim($_POST['team_title'] ?? '');
    $team_description = trim($_POST['team_description'] ?? '');
    $contact_label = trim($_POST['contact_label'] ?? '');
    $contact_title = trim($_POST['contact_title'] ?? '');

    $team1_name = trim($_POST['team1_name'] ?? '');
    $team1_position = trim($_POST['team1_position'] ?? '');
    $team1_bio = trim($_POST['team1_bio'] ?? '');
    $team1_email = trim($_POST['team1_email'] ?? '');
    $team1_phone = trim($_POST['team1_phone'] ?? '');
    $team1_initials = trim($_POST['team1_initials'] ?? '');
    $team2_name = trim($_POST['team2_name'] ?? '');
    $team2_position = trim($_POST['team2_position'] ?? '');
    $team2_bio = trim($_POST['team2_bio'] ?? '');
    $team2_email = trim($_POST['team2_email'] ?? '');
    $team2_phone = trim($_POST['team2_phone'] ?? '');
    $team2_initials = trim($_POST['team2_initials'] ?? '');
    $team3_name = trim($_POST['team3_name'] ?? '');
    $team3_position = trim($_POST['team3_position'] ?? '');
    $team3_bio = trim($_POST['team3_bio'] ?? '');
    $team3_email = trim($_POST['team3_email'] ?? '');
    $team3_phone = trim($_POST['team3_phone'] ?? '');
    $team3_initials = trim($_POST['team3_initials'] ?? '');
    $team4_name = trim($_POST['team4_name'] ?? '');
    $team4_position = trim($_POST['team4_position'] ?? '');
    $team4_bio = trim($_POST['team4_bio'] ?? '');
    $team4_email = trim($_POST['team4_email'] ?? '');
    $team4_phone = trim($_POST['team4_phone'] ?? '');
    $team4_initials = trim($_POST['team4_initials'] ?? '');
    $team5_name = trim($_POST['team5_name'] ?? '');
    $team5_position = trim($_POST['team5_position'] ?? '');
    $team5_bio = trim($_POST['team5_bio'] ?? '');
    $team5_email = trim($_POST['team5_email'] ?? '');
    $team5_phone = trim($_POST['team5_phone'] ?? '');
    $team5_initials = trim($_POST['team5_initials'] ?? '');
    $team6_name = trim($_POST['team6_name'] ?? '');
    $team6_position = trim($_POST['team6_position'] ?? '');
    $team6_bio = trim($_POST['team6_bio'] ?? '');
    $team6_email = trim($_POST['team6_email'] ?? '');
    $team6_phone = trim($_POST['team6_phone'] ?? '');
    $team6_initials = trim($_POST['team6_initials'] ?? '');
    $team7_name = trim($_POST['team7_name'] ?? '');
    $team7_position = trim($_POST['team7_position'] ?? '');
    $team7_bio = trim($_POST['team7_bio'] ?? '');
    $team7_email = trim($_POST['team7_email'] ?? '');
    $team7_phone = trim($_POST['team7_phone'] ?? '');
    $team7_initials = trim($_POST['team7_initials'] ?? '');
    $team8_name = trim($_POST['team8_name'] ?? '');
    $team8_position = trim($_POST['team8_position'] ?? '');
    $team8_bio = trim($_POST['team8_bio'] ?? '');
    $team8_email = trim($_POST['team8_email'] ?? '');
    $team8_phone = trim($_POST['team8_phone'] ?? '');
    $team8_initials = trim($_POST['team8_initials'] ?? '');

    $exists = $db->query("SELECT COUNT(*) AS c FROM landing_content")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    if ((int)$exists > 0) {
        $stmt = $db->prepare("UPDATE landing_content SET hero_title=:ht, hero_subtitle=:hs, hero_bg_url=:hbu, hero_button_text=:hbtn, hero_promo_text=:hpt, hex_img_1=:i1, hex_img_2=:i2, hex_img_3=:i3, booking_title=:bt, booking_subtitle=:bs, offer_weekend_text=:ow, offer_early_text=:oe, gallery_room_url=:gr, gallery_pool_url=:gp, gallery_dining_url=:gd, welcome_label=:wl, welcome_title=:wti, welcome_subtitle=:wsu, welcome_paragraph=:wpa, ceo_name=:cn, ceo_title=:ct, showcase_label=:sl, showcase_title=:sti, showcase_subtitle=:ssu, showcase_paragraph=:spa, footer_text=:ft, rooms_label=:rl, rooms_title=:rt, room_img_1=:ri1, room_img_2=:ri2, room_img_3=:ri3, room_title_1=:rt1, room_title_2=:rt2, room_title_3=:rt3, team_label=:tl, team_title=:tt, team_description=:td, contact_label=:cl, contact_title=:cti, team1_name=:t1n, team1_position=:t1p, team1_bio=:t1b, team1_email=:t1e, team1_phone=:t1ph, team1_initials=:t1i, team2_name=:t2n, team2_position=:t2p, team2_bio=:t2b, team2_email=:t2e, team2_phone=:t2ph, team2_initials=:t2i, team3_name=:t3n, team3_position=:t3p, team3_bio=:t3b, team3_email=:t3e, team3_phone=:t3ph, team3_initials=:t3i, team4_name=:t4n, team4_position=:t4p, team4_bio=:t4b, team4_email=:t4e, team4_phone=:t4ph, team4_initials=:t4i, team5_name=:t5n, team5_position=:t5p, team5_bio=:t5b, team5_email=:t5e, team5_phone=:t5ph, team5_initials=:t5i, team6_name=:t6n, team6_position=:t6p, team6_bio=:t6b, team6_email=:t6e, team6_phone=:t6ph, team6_initials=:t6i, team7_name=:t7n, team7_position=:t7p, team7_bio=:t7b, team7_email=:t7e, team7_phone=:t7ph, team7_initials=:t7i, team8_name=:t8n, team8_position=:t8p, team8_bio=:t8b, team8_email=:t8e, team8_phone=:t8ph, team8_initials=:t8i WHERE id=(SELECT id FROM landing_content ORDER BY id LIMIT 1)");
        $stmt->execute([':ht'=>$hero_title, ':hs'=>$hero_subtitle, ':hbu'=>$hero_bg_url, ':hbtn'=>$hero_button_text, ':hpt'=>$hero_promo_text, ':i1'=>$hex_img_1, ':i2'=>$hex_img_2, ':i3'=>$hex_img_3, ':bt'=>$booking_title, ':bs'=>$booking_subtitle, ':ow'=>$offer_weekend_text, ':oe'=>$offer_early_text, ':gr'=>$gallery_room_url, ':gp'=>$gallery_pool_url, ':gd'=>$gallery_dining_url, ':wl'=>$welcome_label, ':wti'=>$welcome_title, ':wsu'=>$welcome_subtitle, ':wpa'=>$welcome_paragraph, ':cn'=>$ceo_name, ':ct'=>$ceo_title, ':sl'=>$showcase_label, ':sti'=>$showcase_title, ':ssu'=>$showcase_subtitle, ':spa'=>$showcase_paragraph, ':ft'=>$footer_text, ':rl'=>$rooms_label, ':rt'=>$rooms_title, ':ri1'=>$room_img_1, ':ri2'=>$room_img_2, ':ri3'=>$room_img_3, ':rt1'=>$room_title_1, ':rt2'=>$room_title_2, ':rt3'=>$room_title_3, ':tl'=>$team_label, ':tt'=>$team_title, ':td'=>$team_description, ':cl'=>$contact_label, ':cti'=>$contact_title, ':t1n'=>$team1_name, ':t1p'=>$team1_position, ':t1b'=>$team1_bio, ':t1e'=>$team1_email, ':t1ph'=>$team1_phone, ':t1i'=>$team1_initials, ':t2n'=>$team2_name, ':t2p'=>$team2_position, ':t2b'=>$team2_bio, ':t2e'=>$team2_email, ':t2ph'=>$team2_phone, ':t2i'=>$team2_initials, ':t3n'=>$team3_name, ':t3p'=>$team3_position, ':t3b'=>$team3_bio, ':t3e'=>$team3_email, ':t3ph'=>$team3_phone, ':t3i'=>$team3_initials, ':t4n'=>$team4_name, ':t4p'=>$team4_position, ':t4b'=>$team4_bio, ':t4e'=>$team4_email, ':t4ph'=>$team4_phone, ':t4i'=>$team4_initials, ':t5n'=>$team5_name, ':t5p'=>$team5_position, ':t5b'=>$team5_bio, ':t5e'=>$team5_email, ':t5ph'=>$team5_phone, ':t5i'=>$team5_initials, ':t6n'=>$team6_name, ':t6p'=>$team6_position, ':t6b'=>$team6_bio, ':t6e'=>$team6_email, ':t6ph'=>$team6_phone, ':t6i'=>$team6_initials, ':t7n'=>$team7_name, ':t7p'=>$team7_position, ':t7b'=>$team7_bio, ':t7e'=>$team7_email, ':t7ph'=>$team7_phone, ':t7i'=>$team7_initials, ':t8n'=>$team8_name, ':t8p'=>$team8_position, ':t8b'=>$team8_bio, ':t8e'=>$team8_email, ':t8ph'=>$team8_phone, ':t8i'=>$team8_initials]);
    } else {
        $stmt = $db->prepare("INSERT INTO landing_content (hero_title, hero_subtitle, hero_bg_url, hero_button_text, hero_promo_text, hex_img_1, hex_img_2, hex_img_3, booking_title, booking_subtitle, offer_weekend_text, offer_early_text, gallery_room_url, gallery_pool_url, gallery_dining_url, welcome_label, welcome_title, welcome_subtitle, welcome_paragraph, ceo_name, ceo_title, showcase_label, showcase_title, showcase_subtitle, showcase_paragraph, footer_text, rooms_label, rooms_title, room_img_1, room_img_2, room_img_3, room_title_1, room_title_2, room_title_3, team_label, team_title, team_description, contact_label, contact_title, team1_name, team1_position, team1_bio, team1_email, team1_phone, team1_initials, team2_name, team2_position, team2_bio, team2_email, team2_phone, team2_initials, team3_name, team3_position, team3_bio, team3_email, team3_phone, team3_initials, team4_name, team4_position, team4_bio, team4_email, team4_phone, team4_initials, team5_name, team5_position, team5_bio, team5_email, team5_phone, team5_initials, team6_name, team6_position, team6_bio, team6_email, team6_phone, team6_initials, team7_name, team7_position, team7_bio, team7_email, team7_phone, team7_initials, team8_name, team8_position, team8_bio, team8_email, team8_phone, team8_initials) VALUES (:ht,:hs,:hbu,:hbtn,:hpt,:i1,:i2,:i3,:bt,:bs,:ow,:oe,:gr,:gp,:gd,:wl,:wti,:wsu,:wpa,:cn,:ct,:sl,:sti,:ssu,:spa,:ft,:rl,:rt,:ri1,:ri2,:ri3,:rt1,:rt2,:rt3,:tl,:tt,:td,:cl,:cti,:t1n,:t1p,:t1b,:t1e,:t1ph,:t1i,:t2n,:t2p,:t2b,:t2e,:t2ph,:t2i,:t3n,:t3p,:t3b,:t3e,:t3ph,:t3i,:t4n,:t4p,:t4b,:t4e,:t4ph,:t4i,:t5n,:t5p,:t5b,:t5e,:t5ph,:t5i,:t6n,:t6p,:t6b,:t6e,:t6ph,:t6i,:t7n,:t7p,:t7b,:t7e,:t7ph,:t7i,:t8n,:t8p,:t8b,:t8e,:t8ph,:t8i)");
        $stmt->execute([':ht'=>$hero_title, ':hs'=>$hero_subtitle, ':hbu'=>$hero_bg_url, ':hbtn'=>$hero_button_text, ':hpt'=>$hero_promo_text, ':i1'=>$hex_img_1, ':i2'=>$hex_img_2, ':i3'=>$hex_img_3, ':bt'=>$booking_title, ':bs'=>$booking_subtitle, ':ow'=>$offer_weekend_text, ':oe'=>$offer_early_text, ':gr'=>$gallery_room_url, ':gp'=>$gallery_pool_url, ':gd'=>$gallery_dining_url, ':wl'=>$welcome_label, ':wti'=>$welcome_title, ':wsu'=>$welcome_subtitle, ':wpa'=>$welcome_paragraph, ':cn'=>$ceo_name, ':ct'=>$ceo_title, ':sl'=>$showcase_label, ':sti'=>$showcase_title, ':ssu'=>$showcase_subtitle, ':spa'=>$showcase_paragraph, ':ft'=>$footer_text, ':rl'=>$rooms_label, ':rt'=>$rooms_title, ':ri1'=>$room_img_1, ':ri2'=>$room_img_2, ':ri3'=>$room_img_3, ':rt1'=>$room_title_1, ':rt2'=>$room_title_2, ':rt3'=>$room_title_3, ':tl'=>$team_label, ':tt'=>$team_title, ':td'=>$team_description, ':cl'=>$contact_label, ':cti'=>$contact_title, ':t1n'=>$team1_name, ':t1p'=>$team1_position, ':t1b'=>$team1_bio, ':t1e'=>$team1_email, ':t1ph'=>$team1_phone, ':t1i'=>$team1_initials, ':t2n'=>$team2_name, ':t2p'=>$team2_position, ':t2b'=>$team2_bio, ':t2e'=>$team2_email, ':t2ph'=>$team2_phone, ':t2i'=>$team2_initials, ':t3n'=>$team3_name, ':t3p'=>$team3_position, ':t3b'=>$team3_bio, ':t3e'=>$team3_email, ':t3ph'=>$team3_phone, ':t3i'=>$team3_initials, ':t4n'=>$team4_name, ':t4p'=>$team4_position, ':t4b'=>$team4_bio, ':t4e'=>$team4_email, ':t4ph'=>$team4_phone, ':t4i'=>$team4_initials, ':t5n'=>$team5_name, ':t5p'=>$team5_position, ':t5b'=>$team5_bio, ':t5e'=>$team5_email, ':t5ph'=>$team5_phone, ':t5i'=>$team5_initials, ':t6n'=>$team6_name, ':t6p'=>$team6_position, ':t6b'=>$team6_bio, ':t6e'=>$team6_email, ':t6ph'=>$team6_phone, ':t6i'=>$team6_initials, ':t7n'=>$team7_name, ':t7p'=>$team7_position, ':t7b'=>$team7_bio, ':t7e'=>$team7_email, ':t7ph'=>$team7_phone, ':t7i'=>$team7_initials, ':t8n'=>$team8_name, ':t8p'=>$team8_position, ':t8b'=>$team8_bio, ':t8e'=>$team8_email, ':t8ph'=>$team8_phone, ':t8i'=>$team8_initials]);
    }
    $success = 'Landing content saved.';
}

$landing = $db->query("SELECT * FROM landing_content ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$settings = $db->query("SELECT * FROM system_settings ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    $settings = [
        'hotel_name' => '',
        'hotel_address' => '',
        'hotel_phone' => '',
        'hotel_email' => '',
        'tax_rate' => 0,
        'check_in_time' => '14:00:00',
        'check_out_time' => '12:00:00',
        'currency' => 'PHP',
    ];
}

include '../includes/admin_header.php';
?>

<?php if (isset($error)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <?php echo $error; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if (isset($success)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo $success; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card admin-card">
  <div class="card-header">Hotel Profile</div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="action" value="save_settings">
      <div class="col-md-6">
        <label class="form-label">Hotel Name</label>
        <input type="text" class="form-control" name="hotel_name" value="<?php echo htmlspecialchars($settings['hotel_name']); ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" name="hotel_email" value="<?php echo htmlspecialchars($settings['hotel_email']); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" class="form-control" name="hotel_phone" value="<?php echo htmlspecialchars($settings['hotel_phone']); ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Hotel Logo</label>
        <input type="file" class="form-control" name="hotel_logo" accept="image/*">
        <?php if(!empty($settings['hotel_logo'])): ?>
            <div class="mt-2"><img src="../<?php echo htmlspecialchars($settings['hotel_logo']); ?>" alt="Current Logo" style="max-height: 50px;"></div>
        <?php endif; ?>
      </div>
      <div class="col-12">
        <label class="form-label">Address</label>
        <textarea class="form-control" name="hotel_address" rows="2"><?php echo htmlspecialchars($settings['hotel_address']); ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">Tax Rate (%)</label>
        <input type="number" step="0.01" class="form-control" name="tax_rate" value="<?php echo htmlspecialchars($settings['tax_rate']); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Check-in Time</label>
        <input type="time" class="form-control" name="check_in_time" value="<?php echo htmlspecialchars($settings['check_in_time']); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Check-out Time</label>
        <input type="time" class="form-control" name="check_out_time" value="<?php echo htmlspecialchars($settings['check_out_time']); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Currency</label>
        <input type="text" class="form-control" name="currency" value="<?php echo htmlspecialchars($settings['currency']); ?>">
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary">Save Settings</button>
      </div>
    </form>
  </div>
</div>

<div class="card admin-card mt-4">
  <div class="card-header">Change Password</div>
  <div class="card-body">
    <form method="POST" class="row g-3">
        <input type="hidden" name="action" value="change_password">
        <div class="col-md-4">
            <label class="form-label">Current Password</label>
            <input type="password" class="form-control" name="current_password" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">New Password</label>
            <input type="password" class="form-control" name="new_password" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Confirm New Password</label>
            <input type="password" class="form-control" name="confirm_password" required>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Update Password</button>
        </div>
    </form>
  </div>
</div>

<div class="card admin-card mt-4">
  <div class="card-header">Database Backup</div>
  <div class="card-body">
    <form method="POST" class="row g-3">
        <input type="hidden" name="action" value="backup_database">
        <div class="col-12">
            <p class="text-muted">Click the button below to generate and download a full SQL backup of the database.</p>
            <button type="submit" class="btn btn-primary">Generate Backup</button>
        </div>
    </form>
  </div>
</div>

    <div class="card admin-card mt-4">
      <div class="card-header">Landing Page Content</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="row g-3">
          <input type="hidden" name="action" value="save_landing_content">
      <div class="col-12"><h5 class="mb-3">Hero</h5></div>
      <div class="col-md-6">
        <label class="form-label">Hero Title</label>
        <input type="text" class="form-control" name="hero_title" value="<?php echo htmlspecialchars($landing['hero_title'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['hero_title'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Hero Subtitle</label>
        <input type="text" class="form-control" name="hero_subtitle" value="<?php echo htmlspecialchars($landing['hero_subtitle'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['hero_subtitle'] ?? ''); ?></small>
      </div>
      <div class="col-md-8">
        <label class="form-label">Hero Background Image</label>
        <input type="file" class="form-control" name="hero_bg_url" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['hero_bg_url'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Hero Button Text</label>
        <input type="text" class="form-control" name="hero_button_text" value="<?php echo htmlspecialchars($landing['hero_button_text'] ?? 'Book Now!'); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['hero_button_text'] ?? 'Book Now!'); ?></small>
      </div>
      <div class="col-12">
        <label class="form-label">Hero Promo Text</label>
        <input type="text" class="form-control" name="hero_promo_text" value="<?php echo htmlspecialchars($landing['hero_promo_text'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['hero_promo_text'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Hex Image 1</label>
        <input type="file" class="form-control" name="hex_img_1" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['hex_img_1'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Hex Image 2</label>
        <input type="file" class="form-control" name="hex_img_2" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['hex_img_2'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Hex Image 3</label>
        <input type="file" class="form-control" name="hex_img_3" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['hex_img_3'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Booking</h5></div>
      <div class="col-md-6">
        <label class="form-label">Booking Title</label>
        <input type="text" class="form-control" name="booking_title" value="<?php echo htmlspecialchars($landing['booking_title'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['booking_title'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Booking Subtitle</label>
        <input type="text" class="form-control" name="booking_subtitle" value="<?php echo htmlspecialchars($landing['booking_subtitle'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['booking_subtitle'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Offers</h5></div>
      <div class="col-md-6">
        <label class="form-label">Weekend Offer Text</label>
        <input type="text" class="form-control" name="offer_weekend_text" value="<?php echo htmlspecialchars($landing['offer_weekend_text'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['offer_weekend_text'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Early Bird Offer Text</label>
        <input type="text" class="form-control" name="offer_early_text" value="<?php echo htmlspecialchars($landing['offer_early_text'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['offer_early_text'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Gallery</h5></div>
      <div class="col-md-4">
        <label class="form-label">Gallery Room Image</label>
        <input type="file" class="form-control" name="gallery_room_url" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['gallery_room_url'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Gallery Pool Image</label>
        <input type="file" class="form-control" name="gallery_pool_url" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['gallery_pool_url'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Gallery Dining Image</label>
        <input type="file" class="form-control" name="gallery_dining_url" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['gallery_dining_url'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Welcome</h5></div>
      <div class="col-md-6">
        <label class="form-label">Welcome Section Label</label>
        <input type="text" class="form-control" name="welcome_label" value="<?php echo htmlspecialchars($landing['welcome_label'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['welcome_label'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Welcome Title</label>
        <input type="text" class="form-control" name="welcome_title" value="<?php echo htmlspecialchars($landing['welcome_title'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['welcome_title'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Welcome Subtitle</label>
        <input type="text" class="form-control" name="welcome_subtitle" value="<?php echo htmlspecialchars($landing['welcome_subtitle'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['welcome_subtitle'] ?? ''); ?></small>
      </div>
      <div class="col-12">
        <label class="form-label">Welcome Paragraph</label>
        <textarea class="form-control" name="welcome_paragraph" rows="3"><?php echo htmlspecialchars($landing['welcome_paragraph'] ?? ''); ?></textarea>
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['welcome_paragraph'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Leadership</h5></div>
      <div class="col-md-6">
        <label class="form-label">CEO Name</label>
        <input type="text" class="form-control" name="ceo_name" value="<?php echo htmlspecialchars($landing['ceo_name'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['ceo_name'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">CEO Title</label>
        <input type="text" class="form-control" name="ceo_title" value="<?php echo htmlspecialchars($landing['ceo_title'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['ceo_title'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Rooms</h5></div>
      <div class="col-md-6">
        <label class="form-label">Rooms Section Label</label>
        <input type="text" class="form-control" name="rooms_label" value="<?php echo htmlspecialchars($landing['rooms_label'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['rooms_label'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Rooms Section Title</label>
        <input type="text" class="form-control" name="rooms_title" value="<?php echo htmlspecialchars($landing['rooms_title'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['rooms_title'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Room 1 Image</label>
        <input type="file" class="form-control" name="room_img_1" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['room_img_1'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Room 2 Image</label>
        <input type="file" class="form-control" name="room_img_2" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['room_img_2'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Room 3 Image</label>
        <input type="file" class="form-control" name="room_img_3" accept="image/*">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['room_img_3'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Room 1 Title</label>
        <input type="text" class="form-control" name="room_title_1" value="<?php echo htmlspecialchars($landing['room_title_1'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['room_title_1'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Room 2 Title</label>
        <input type="text" class="form-control" name="room_title_2" value="<?php echo htmlspecialchars($landing['room_title_2'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['room_title_2'] ?? ''); ?></small>
      </div>
      <div class="col-md-4">
        <label class="form-label">Room 3 Title</label>
        <input type="text" class="form-control" name="room_title_3" value="<?php echo htmlspecialchars($landing['room_title_3'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['room_title_3'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Showcase</h5></div>
      <div class="col-md-6">
        <label class="form-label">Showcase Label</label>
        <input type="text" class="form-control" name="showcase_label" value="<?php echo htmlspecialchars($landing['showcase_label'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['showcase_label'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Showcase Title</label>
        <input type="text" class="form-control" name="showcase_title" value="<?php echo htmlspecialchars($landing['showcase_title'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['showcase_title'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Showcase Subtitle</label>
        <input type="text" class="form-control" name="showcase_subtitle" value="<?php echo htmlspecialchars($landing['showcase_subtitle'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['showcase_subtitle'] ?? ''); ?></small>
      </div>
      <div class="col-12">
        <label class="form-label">Showcase Paragraph</label>
        <textarea class="form-control" name="showcase_paragraph" rows="3"><?php echo htmlspecialchars($landing['showcase_paragraph'] ?? ''); ?></textarea>
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['showcase_paragraph'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Footer</h5></div>
      <div class="col-12">
        <label class="form-label">Footer Text</label>
        <input type="text" class="form-control" name="footer_text" value="<?php echo htmlspecialchars($landing['footer_text'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['footer_text'] ?? ''); ?></small>
      </div>
      <div class="col-12"><h5 class="mb-3">Team</h5></div>
      <div class="col-md-6">
        <label class="form-label">Team Section Label</label>
        <input type="text" class="form-control" name="team_label" value="<?php echo htmlspecialchars($landing['team_label'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['team_label'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Team Section Title</label>
        <input type="text" class="form-control" name="team_title" value="<?php echo htmlspecialchars($landing['team_title'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['team_title'] ?? ''); ?></small>
      </div>
      <div class="col-12">
        <label class="form-label">Team Section Description</label>
        <input type="text" class="form-control" name="team_description" value="<?php echo htmlspecialchars($landing['team_description'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['team_description'] ?? ''); ?></small>
      </div>
      <div class="col-12 d-flex justify-content-between align-items-center">
        <h5 class="mb-3">Team Members</h5>
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#teamMembersModal">Edit Team Members</button>
      </div>
      
      <div class="col-12"><h5 class="mb-3">Contact</h5></div>
      <div class="col-md-6">
        <label class="form-label">Contact Section Label</label>
        <input type="text" class="form-control" name="contact_label" value="<?php echo htmlspecialchars($landing['contact_label'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['contact_label'] ?? ''); ?></small>
      </div>
      <div class="col-md-6">
        <label class="form-label">Contact Section Title</label>
        <input type="text" class="form-control" name="contact_title" value="<?php echo htmlspecialchars($landing['contact_title'] ?? ''); ?>">
        <small class="text-muted">Current: <?php echo htmlspecialchars($landing['contact_title'] ?? ''); ?></small>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary">Save Landing Content</button>
      </div>
    </form>
  </div>
</div>

<?php
try { $members = $db->query("SELECT * FROM team_members ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $members = []; }
?>
<div class="modal fade" id="teamMembersModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Team Members</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" class="row g-3">
          <input type="hidden" name="action" value="add_team_member">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input type="text" class="form-control" name="name" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Position</label>
            <input type="text" class="form-control" name="position">
          </div>
          <div class="col-12">
            <label class="form-label">Bio</label>
            <textarea class="form-control" name="bio" rows="3"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email">
          </div>
          <div class="col-md-4">
            <label class="form-label">Phone</label>
            <input type="text" class="form-control" name="phone">
          </div>
          <div class="col-md-2">
            <label class="form-label">Initials</label>
            <input type="text" class="form-control" name="initials" maxlength="8">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">Add Member</button>
          </div>
        </form>
        <hr>
        <div class="list-group">
          <?php foreach ($members as $m): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div><?php echo htmlspecialchars($m['name']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($m['position']); ?></small>
              </div>
              <form method="POST" onsubmit="return confirm('Delete this member?');">
                <input type="hidden" name="action" value="delete_team_member">
                <input type="hidden" name="member_id" value="<?php echo (int)$m['id']; ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
          <?php if (empty($members)): ?>
            <div class="list-group-item">No team members added.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
