FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Copy source code (needed for post-install-cmd)
COPY src/ src/
COPY public/ public/

# Create database directory before composer install (needed for post-install-cmd)
RUN mkdir -p database && chmod 755 database

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy remaining application code
COPY . .

# Create database directory and set permissions
RUN mkdir -p database && chmod 755 database

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Configure Apache document root to public directory
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Create startup script to configure Apache port dynamically
RUN echo '#!/bin/bash\n\
PORT=${PORT:-80}\n\
echo "Listen $PORT" > /etc/apache2/ports.conf\n\
sed -i "s/80/$PORT/g" /etc/apache2/sites-available/000-default.conf\n\
apache2-foreground' > /usr/local/bin/start-apache.sh && \
    chmod +x /usr/local/bin/start-apache.sh

# Expose port (Render will set PORT env var)
EXPOSE 80

# Start Apache with dynamic port configuration
CMD ["/usr/local/bin/start-apache.sh"]