<?php
// telegram_webhook.php (Standalone version for Render.com)
// [อัปเกรด] รองรับคำสั่ง /start, /getchatid, และ /dashboard

// --- การตั้งค่า ---
$botToken = getenv('TELEGRAM_BOT_TOKEN'); // <-- อ่าน Token จาก Environment Variable
if (!$botToken) {
    error_log("FATAL: TELEGRAM_BOT_TOKEN environment variable is not set.");
    http_response_code(500);
    exit("Bot Token not configured.");
}
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . $botToken . '/');

// --- [ใหม่] URL ของ Google Apps Script Web App (แดชบอร์ด) ---
// (นี่คือ URL ที่เราใช้ในขั้นตอนที่ 1 และ 2)
define('WEB_APP_URL', 'https://script.google.com/macros/s/AKfycbxcHhkU7p9Qxg9z8kDgXSujOR306DJFCr4CWfGjFRHmA5CbYhR0-rDbOJdiUDeep00x/exec');

// --- การตั้งค่า Error Log (เหมือนเดิม) ---
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('display_errors', 0);

/**
 * [อัปเกรด] ฟังก์ชันส่งข้อความกลับ
 * เพิ่ม $reply_markup (สำหรับส่งปุ่ม) และเปลี่ยนไปส่งแบบ JSON
 * @param string $chat_id
 * @param string $message_text
 * @param array|null $reply_markup (Optional)
 * @return bool
 */
function send_reply(string $chat_id, string $message_text, ?array $reply_markup = null): bool {
    if (empty($chat_id) || empty($message_text)) {
        error_log("send_reply: Invalid chat_id or message_text.");
        return false;
    }

    $params = [
        'chat_id' => $chat_id,
        'text' => $message_text,
        'parse_mode' => 'HTML' //
    ];

    // [ใหม่] ถ้ามีปุ่ม (inline_keyboard) ให้เพิ่มเข้าไปใน params
    if ($reply_markup) {
        $params['reply_markup'] = $reply_markup;
    }

    $ch = curl_init(TELEGRAM_API_URL . 'sendMessage');
    curl_setopt($ch, CURLOPT_POST, 1);
    // [อัปเกรด] ส่งเป็น JSON payload เพื่อรองรับ reply_markup ที่ซับซ้อน
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

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

// 1. รับข้อมูล JSON ที่ Telegram ส่งมา (เหมือนเดิม)
$update_json = file_get_contents('php://input');
$update = json_decode($update_json, true);

// 2. ตรวจสอบข้อมูล message และ chat_id (เหมือนเดิม)
if (!isset($update['message']) || !isset($update['message']['chat']['id'])) {
    error_log("Webhook received invalid update or not a message.");
    http_response_code(200);
    exit();
}

// 3. ดึงข้อมูล (เหมือนเดิม)
$chat_id = (string) $update['message']['chat']['id'];
$message_text = trim($update['message']['text'] ?? ''); // [ใหม่] ใช้ trim() เพื่อลบช่องว่าง
$first_name = $update['message']['from']['first_name'] ?? 'ผู้ใช้งาน';

error_log("Webhook: Received message from Chat ID {$chat_id}. Text: {$message_text}");

// 4. [อัปเกรด] ตรรกะการตอบกลับตามคำสั่ง
if ($message_text === '/start' || $message_text === '/getchatid') {
    // --- ตอบกลับด้วย Chat ID (เหมือนเดิม) ---
    $reply_message = "สวัสดี คุณ {$first_name}! 👋\n\n";
    $reply_message .= "Chat ID ของคุณสำหรับเชื่อมต่อระบบคือ:\n";
    $reply_message .= "<code>" . htmlspecialchars($chat_id) . "</code>\n\n";
    $reply_message .= "กรุณานำ ID นี้ไปใช้เชื่อมต่อในระบบ APSCCOM (Intranet)";
    
    send_reply($chat_id, $reply_message);

} elseif ($message_text === '/dashboard') {
    // --- [ใหม่] ตอบกลับด้วยปุ่มเปิด Web App (แดชบอร์ด) ---
    $reply_message = "📊 สวัสดี คุณ {$first_name}!\n\nกรุณากดปุ่มด้านล่างเพื่อเปิดแดชบอร์ดข้อมูลคดีค้าง (สำหรับผู้บริหาร) ครับ";
    
    // สร้างปุ่ม Web App
    $reply_markup = [
        'inline_keyboard' => [
            [
                // ปุ่มนี้จะเปิด Web App (Index.html)
                ['text' => '🚀 เปิดแดชบอร์ด', 'web_app' => ['url' => WEB_APP_URL]]
            ]
        ]
    ];
    
    send_reply($chat_id, $reply_message, $reply_markup);

} else {
    // --- [ใหม่] ตอบกลับเมื่อพิมพ์ข้อความอื่นๆ ---
    $reply_message = "กรุณาใช้คำสั่ง:\n";
    $reply_message .= "• `/start` หรือ `/getchatid` เพื่อขอ Chat ID\n";
    $reply_message .= "• `/dashboard` เพื่อเปิดแดชบอร์ดข้อมูลคดีค้าง";
    
    send_reply($chat_id, $reply_message);
}

// 5. ตอบกลับ Telegram ว่ารับทราบแล้ว (เหมือนเดิม)
http_response_code(200);
echo json_encode(['status' => 'ok']);
exit();

?>
