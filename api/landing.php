<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

$db = db();


$landing = null;
if ($db) {
    try {
        $landing = $db->query("SELECT * FROM landing_content ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $landing = null;
    }
}

$settings = null;
if ($db) {
    try {
        $settings = $db->query("SELECT hotel_name, hotel_address, hotel_phone, hotel_email FROM system_settings ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $settings = null;
    }
}

$content = [];
if ($landing) {
    $title = trim($landing['hero_title'] ?? '');
    $words = [];
    if ($title !== '') {
        if (strpos($title, '|') !== false) {
            $parts = explode('|', $title);
        } else {
            $parts = [$title];
        }
        foreach ($parts as $p) { $p = trim($p); if ($p !== '') { $words[] = $p; } }
    }
    if (!empty($words)) { $content['hero_words'] = $words; }
    if (!empty($landing['hero_subtitle'])) { $content['hero_subtext'] = $landing['hero_subtitle']; }
    if (!empty($landing['hero_button_text'])) { $content['cta_label'] = $landing['hero_button_text']; }
    if (!empty($landing['hero_promo_text'])) { $content['hero_promo_text'] = $landing['hero_promo_text']; }
    if (!empty($landing['hero_bg_url'])) { $content['hero_bg_url'] = $landing['hero_bg_url']; }
    if (!empty($landing['hex_img_1'])) { $content['welcome_img_1'] = $landing['hex_img_1']; }
    if (!empty($landing['hex_img_2'])) { $content['welcome_img_2'] = $landing['hex_img_2']; }
    if (!empty($landing['hex_img_3'])) { $content['welcome_img_3'] = $landing['hex_img_3']; }
    if (!empty($landing['booking_title'])) { $content['booking_title'] = $landing['booking_title']; }
    if (!empty($landing['booking_subtitle'])) { $content['booking_subtitle'] = $landing['booking_subtitle']; }
    if (!empty($landing['offer_weekend_text'])) { $content['offer_weekend_text'] = $landing['offer_weekend_text']; }
    if (!empty($landing['offer_early_text'])) { $content['offer_early_text'] = $landing['offer_early_text']; }
    if (!empty($landing['gallery_room_url'])) { $content['gallery_room_url'] = $landing['gallery_room_url']; }
    if (!empty($landing['gallery_pool_url'])) { $content['gallery_pool_url'] = $landing['gallery_pool_url']; }
    if (!empty($landing['gallery_dining_url'])) { $content['gallery_dining_url'] = $landing['gallery_dining_url']; }
    if (!empty($landing['welcome_label'])) { $content['welcome_label'] = $landing['welcome_label']; }
    if (!empty($landing['welcome_title'])) { $content['welcome_title'] = $landing['welcome_title']; }
    if (!empty($landing['welcome_subtitle'])) { $content['welcome_subtitle'] = $landing['welcome_subtitle']; }
    if (!empty($landing['welcome_paragraph'])) { $content['welcome_paragraph'] = $landing['welcome_paragraph']; }
    if (!empty($landing['ceo_name'])) { $content['ceo_name'] = $landing['ceo_name']; }
    if (!empty($landing['ceo_title'])) { $content['ceo_title'] = $landing['ceo_title']; }
    if (!empty($landing['showcase_label'])) { $content['showcase_label'] = $landing['showcase_label']; }
    if (!empty($landing['showcase_title'])) { $content['showcase_title'] = $landing['showcase_title']; }
    if (!empty($landing['showcase_subtitle'])) { $content['showcase_subtitle'] = $landing['showcase_subtitle']; }
    if (!empty($landing['showcase_paragraph'])) { $content['showcase_paragraph'] = $landing['showcase_paragraph']; }
    if (!empty($landing['footer_text'])) { $content['footer_text'] = $landing['footer_text']; }
    if (!empty($landing['rooms_label'])) { $content['rooms_label'] = $landing['rooms_label']; }
    if (!empty($landing['rooms_title'])) { $content['rooms_title'] = $landing['rooms_title']; }
    if (!empty($landing['room_img_1'])) { $content['room_img_1'] = $landing['room_img_1']; }
    if (!empty($landing['room_img_2'])) { $content['room_img_2'] = $landing['room_img_2']; }
    if (!empty($landing['room_img_3'])) { $content['room_img_3'] = $landing['room_img_3']; }
    if (!empty($landing['room_title_1'])) { $content['room_title_1'] = $landing['room_title_1']; }
    if (!empty($landing['room_title_2'])) { $content['room_title_2'] = $landing['room_title_2']; }
    if (!empty($landing['room_title_3'])) { $content['room_title_3'] = $landing['room_title_3']; }
    if (!empty($landing['team_label'])) { $content['team_label'] = $landing['team_label']; }
    if (!empty($landing['team_title'])) { $content['team_title'] = $landing['team_title']; }
    if (!empty($landing['team_description'])) { $content['team_description'] = $landing['team_description']; }
    if (!empty($landing['team1_name'])) { $content['team1_name'] = $landing['team1_name']; }
    if (!empty($landing['team1_position'])) { $content['team1_position'] = $landing['team1_position']; }
    if (!empty($landing['team1_bio'])) { $content['team1_bio'] = $landing['team1_bio']; }
    if (!empty($landing['team1_email'])) { $content['team1_email'] = $landing['team1_email']; }
    if (!empty($landing['team1_phone'])) { $content['team1_phone'] = $landing['team1_phone']; }
    if (!empty($landing['team1_initials'])) { $content['team1_initials'] = $landing['team1_initials']; }
    if (!empty($landing['team2_name'])) { $content['team2_name'] = $landing['team2_name']; }
    if (!empty($landing['team2_position'])) { $content['team2_position'] = $landing['team2_position']; }
    if (!empty($landing['team2_bio'])) { $content['team2_bio'] = $landing['team2_bio']; }
    if (!empty($landing['team2_email'])) { $content['team2_email'] = $landing['team2_email']; }
    if (!empty($landing['team2_phone'])) { $content['team2_phone'] = $landing['team2_phone']; }
    if (!empty($landing['team2_initials'])) { $content['team2_initials'] = $landing['team2_initials']; }
    if (!empty($landing['team3_name'])) { $content['team3_name'] = $landing['team3_name']; }
    if (!empty($landing['team3_position'])) { $content['team3_position'] = $landing['team3_position']; }
    if (!empty($landing['team3_bio'])) { $content['team3_bio'] = $landing['team3_bio']; }
    if (!empty($landing['team3_email'])) { $content['team3_email'] = $landing['team3_email']; }
    if (!empty($landing['team3_phone'])) { $content['team3_phone'] = $landing['team3_phone']; }
    if (!empty($landing['team3_initials'])) { $content['team3_initials'] = $landing['team3_initials']; }
    if (!empty($landing['team4_name'])) { $content['team4_name'] = $landing['team4_name']; }
    if (!empty($landing['team4_position'])) { $content['team4_position'] = $landing['team4_position']; }
    if (!empty($landing['team4_bio'])) { $content['team4_bio'] = $landing['team4_bio']; }
    if (!empty($landing['team4_email'])) { $content['team4_email'] = $landing['team4_email']; }
    if (!empty($landing['team4_phone'])) { $content['team4_phone'] = $landing['team4_phone']; }
    if (!empty($landing['team4_initials'])) { $content['team4_initials'] = $landing['team4_initials']; }
    if (!empty($landing['team5_name'])) { $content['team5_name'] = $landing['team5_name']; }
    if (!empty($landing['team5_position'])) { $content['team5_position'] = $landing['team5_position']; }
    if (!empty($landing['team5_bio'])) { $content['team5_bio'] = $landing['team5_bio']; }
    if (!empty($landing['team5_email'])) { $content['team5_email'] = $landing['team5_email']; }
    if (!empty($landing['team5_phone'])) { $content['team5_phone'] = $landing['team5_phone']; }
    if (!empty($landing['team5_initials'])) { $content['team5_initials'] = $landing['team5_initials']; }
    if (!empty($landing['team6_name'])) { $content['team6_name'] = $landing['team6_name']; }
    if (!empty($landing['team6_position'])) { $content['team6_position'] = $landing['team6_position']; }
    if (!empty($landing['team6_bio'])) { $content['team6_bio'] = $landing['team6_bio']; }
    if (!empty($landing['team6_email'])) { $content['team6_email'] = $landing['team6_email']; }
    if (!empty($landing['team6_phone'])) { $content['team6_phone'] = $landing['team6_phone']; }
    if (!empty($landing['team6_initials'])) { $content['team6_initials'] = $landing['team6_initials']; }
    if (!empty($landing['team7_name'])) { $content['team7_name'] = $landing['team7_name']; }
    if (!empty($landing['team7_position'])) { $content['team7_position'] = $landing['team7_position']; }
    if (!empty($landing['team7_bio'])) { $content['team7_bio'] = $landing['team7_bio']; }
    if (!empty($landing['team7_email'])) { $content['team7_email'] = $landing['team7_email']; }
    if (!empty($landing['team7_phone'])) { $content['team7_phone'] = $landing['team7_phone']; }
    if (!empty($landing['team7_initials'])) { $content['team7_initials'] = $landing['team7_initials']; }
    if (!empty($landing['team8_name'])) { $content['team8_name'] = $landing['team8_name']; }
    if (!empty($landing['team8_position'])) { $content['team8_position'] = $landing['team8_position']; }
    if (!empty($landing['team8_bio'])) { $content['team8_bio'] = $landing['team8_bio']; }
    if (!empty($landing['team8_email'])) { $content['team8_email'] = $landing['team8_email']; }
    if (!empty($landing['team8_phone'])) { $content['team8_phone'] = $landing['team8_phone']; }
    if (!empty($landing['team8_initials'])) { $content['team8_initials'] = $landing['team8_initials']; }
    if (!empty($landing['contact_label'])) { $content['contact_label'] = $landing['contact_label']; }
    if (!empty($landing['contact_title'])) { $content['contact_title'] = $landing['contact_title']; }
}

if ($settings) {
    if (!empty($settings['hotel_name'])) { $content['footer_title'] = $settings['hotel_name']; }
    if (!empty($settings['hotel_address'])) { $content['footer_address'] = $settings['hotel_address']; }
    if (!empty($settings['hotel_phone'])) { $content['footer_phone'] = $settings['hotel_phone']; }
    if (!empty($settings['hotel_email'])) { $content['footer_email'] = $settings['hotel_email']; }
    $copyTitle = $settings['hotel_name'] ?? 'Hotel';
    $content['footer_copy'] = '© ' . date('Y') . ' ' . $copyTitle . '. All rights reserved.';
}

// Dynamic team members (admin-managed)
if ($db) {
    try {
        $members = $db->query("SELECT name, position, bio, email, phone, initials FROM team_members WHERE visible = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($members)) {
            $content['team_members'] = $members;
        }
    } catch (Throwable $e) {
        // ignore
    }
}

echo json_encode(['ok' => true, 'content' => $content]);
?>
