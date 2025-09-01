FROM php:8.3-apache

# 必要なパッケージ・拡張
RUN apt-get update && apt-get install -y \
    zip unzip curl git libpq-dev libzip4 libzip-dev libxml2-dev libcurl4-openssl-dev \
    libonig-dev supervisor \
    && docker-php-ext-install pdo pdo_pgsql zip dom mbstring xml \
    && apt-get clean && rm -rf /var/lib/apt/lists/*





# Composer追加
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache VirtualHostの修正
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    \n\
    # ectokorea 경로 처리를 위한 Alias 설정\n\
    Alias /ectokorea /var/www/html/public\n\
    \n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
        \n\
        # ectokorea 경로를 위한 추가 rewrite 규칙\n\
        RewriteEngine On\n\
        RewriteCond %{REQUEST_URI} ^/ectokorea/(.*)$\n\
        RewriteRule ^(.*)$ /%1 [L]\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# mod_rewrite 有効化（.htaccessが効くように）
RUN a2enmod rewrite

# Laravel配置
WORKDIR /var/www/html
COPY . .

# Supervisor 설정 복사
COPY supervisor-ectokorea-worker.conf /etc/supervisor/conf.d/

# 권限とキャッシュのクリア（Laravel系）
RUN chown -R www-data:www-data /var/www/html \
    && composer install --no-dev --optimize-autoloader \
    && php artisan config:cache

# Supervisor 및 Apache 시작 스크립트
COPY start-services.sh /usr/local/bin/start-services.sh
RUN chmod +x /usr/local/bin/start-services.sh

CMD ["/usr/local/bin/start-services.sh"]
