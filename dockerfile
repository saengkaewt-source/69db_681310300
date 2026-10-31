FROM php:8.1-apache
# ติดตั้ง mysqli extension เพื่อให้ PHP เชื่อมต่อกับ MySQL ได้
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql