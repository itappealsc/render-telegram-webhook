<?php
// telegram_webhook.php (Standalone version for Render.com)
// ไฟล์นี้ทำหน้าที่ "รับ" ข้อมูลจาก Telegram และ "ตอบ" กลับด้วย Chat ID เท่านั้น
// *** ไม่ต้องเชื่อมต่อกับฐานข้อมูลหลัก ***

// --- การตั้งค่า ---
// ❗️❗️ ไม่ต้องใส่ Token ตรงนี้แล้ว เราจะตั้งค่าผ่าน Environment Variable บน Render ❗️❗️
// define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE'); // <--- เอาบรรทัดนี้ออก หรือ Comment out
$botToken = getenv('TELEGRAM_BOT_TOKEN'); // <-- อ่าน Token จาก Environment Variable
if (!$botToken) {
    error_log("FATAL: TELEGRAM_BOT_TOKEN environment variable is not set.");
    http_response_code(500); // Internal Server Error
    exit("Bot Token not configured.");
}
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . $botToken . '/');

// --- การตั้งค่า Error Log (สำคัญสำหรับ Debug บน Cloud) ---
ini_set('log_errors', 1);
error_reporting(E_ALL); // รายงาน Error ทั้งหมดลง Log
ini_set('display_errors', 0); // *** ห้ามแสดง Error ออกหน้าจอเด็ดขาด ***

// --- ฟังก์ชันส่งข้อความกลับ (ใช้ cURL ภายในไฟล์นี้เลย) ---
function send_reply(string $chat_id, string $message_text): bool {
    if (empty($chat_id) || empty($message_text)) {
        error_log("send_reply: Invalid chat_id or message_text.");
        return false;
    }

    $params = [
        'chat_id' => $chat_id,
        'text' => $message_text,
        'parse_mode' => 'HTML' // ใช้ HTML formatting (สำหรับ <code>)
    ];

    $ch = curl_init(TELEGRAM_API_URL . 'sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // ควรเปิดไว้

    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        error_log("send_reply: Successfully sent message to Chat ID {$chat_id}.");
        return true;
    } else {
        error_log("send_reply: Failed to send message to Chat ID {$chat_id}. HTTP Code: {$http_code}. cURL Error: {$curl_error}. Response: {$response_body}");
        return false;
    }
}

// --- ส่วนหลัก: รับข้อมูลและตอบกลับ ---

// 1. รับข้อมูล JSON ที่ Telegram ส่งมา
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

// 2. ตรวจสอบข้อมูล message และ chat_id
if (!isset($update['message']) || !isset($update['message']['chat']['id'])) {
    error_log("Webhook received invalid update or not a message.");
    http_response_code(200); // ตอบ OK ให้ Telegram แม้จะไม่ประมวลผล
    exit();
}

// 3. ดึงข้อมูล
$chat_id = $update['message']['chat']['id'];
$message_text = $update['message']['text'] ?? ''; // ข้อความที่ผู้ใช้พิมพ์
$first_name = $update['message']['from']['first_name'] ?? 'ผู้ใช้งาน';

error_log("Webhook: Received message from Chat ID {$chat_id}. Text: {$message_text}");

// 4. สร้างข้อความตอบกลับ (ส่ง Chat ID เสมอ เมื่อมีการส่งข้อความมา)
$reply_message = "สวัสดี คุณ {$first_name}! 👋\n\n";
$reply_message .= "Chat ID ของคุณสำหรับเชื่อมต่อระบบคือ:\n";
$reply_message .= "<code>" . htmlspecialchars($chat_id) . "</code>\n\n"; // ใช้ <code> และ htmlspecialchars ป้องกัน XSS
$reply_message .= "กรุณานำ ID นี้ไปใช้เชื่อมต่อในระบบ APSCCOM (Intranet)";

// 5. ส่งข้อความตอบกลับ
send_reply((string)$chat_id, $reply_message);

// 6. ตอบกลับ Telegram ว่ารับทราบแล้ว
http_response_code(200);
echo json_encode(['status' => 'ok']);
exit();

?>