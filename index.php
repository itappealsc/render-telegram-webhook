<?php
// This file ensures Render detects the project as PHP.
// The actual webhook logic is in telegram_webhook.php
http_response_code(404); // Return Not Found if accessed directly
echo "Not Found.";
?>