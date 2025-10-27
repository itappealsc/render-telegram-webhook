# ใช้ Official PHP image ที่มี Apache ติดตั้งมาให้
FROM php:8.3-apache

# Copy โค้ด PHP ของเราเข้าไปใน web root ของ Apache ใน Container
# เปลี่ยน 'telegram_webhook.php' ถ้าชื่อไฟล์ของคุณต่างออกไป
COPY telegram_webhook.php /var/www/html/telegram_webhook.php

# (Optional) ตั้งค่า PHP configurations ถ้าจำเป็น (เช่น error logging)
# RUN echo "log_errors=On" >> /usr/local/etc/php/conf.d/docker-php-config.ini
# RUN echo "error_log=/proc/self/fd/2" >> /usr/local/etc/php/conf.d/docker-php-config.ini # ส่ง Log ไปที่ Docker logs

# Apache จะรันอัตโนมัติเมื่อ Container เริ่มทำงาน ไม่ต้องใส่ CMD หรือ ENTRYPOINT เพิ่มเติม