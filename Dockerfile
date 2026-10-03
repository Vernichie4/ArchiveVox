FROM php:8.2-apache

# Install system dependencies for PHP extensions and transcription
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    python3 \
    python3-pip \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Install Whisper for audio transcription
RUN pip3 install --no-cache-dir openai-whisper

# Install required PHP extensions
# - pdo: Database abstraction layer
# - pdo_mysql: MySQL driver (required for Railway MySQL)
# - pdo_pgsql: PostgreSQL driver (required by composer.json)
# - pgsql: PostgreSQL client (required by composer.json)
# - gd: Image processing (required by PhpSpreadsheet)
# - mbstring: Multibyte string functions (used in php/auth/config.php)
# - zip: ZipArchive for file operations (used in student import)
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql gd mbstring zip

# Enable Apache mod_rewrite for clean URLs
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better Docker layer caching
COPY composer.json composer.lock ./

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application files
COPY . .

# Create persistent data directories with proper permissions
RUN mkdir -p uploads/materials uploads/audio uploads/temp logs database && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Set PHP timezone to match application (Asia/Manila)
RUN echo "date.timezone=Asia/Manila" > /usr/local/etc/php/conf.d/timezone.ini

# Configure Apache to use Railway's PORT at runtime
# Create a startup script that reads PORT environment variable
# Cache bust: fix-apache-config-v7
RUN cat > /usr/local/bin/start-apache.sh << 'EOF'
#!/bin/bash
# Disable conflicting MPMs to avoid "More than one MPM loaded" error
a2dismod mpm_event mpm_worker
a2enmod mpm_prefork
# Get Railway PORT or default to 80
PORT=${PORT:-80}
# Clear existing Apache config
rm -f /etc/apache2/sites-enabled/*
# Write new Apache configuration - only listen on PORT (not both 80 and PORT)
echo "Listen $PORT" > /etc/apache2/sites-available/000-default.conf
echo "<VirtualHost *:$PORT>" >> /etc/apache2/sites-available/000-default.conf
echo "    DocumentRoot /var/www/html" >> /etc/apache2/sites-available/000-default.conf
echo "    <Directory /var/www/html>" >> /etc/apache2/sites-available/000-default.conf
echo "        AllowOverride All" >> /etc/apache2/sites-available/000-default.conf
echo "        Require all granted" >> /etc/apache2/sites-available/000-default.conf
echo "    </Directory>" >> /etc/apache2/sites-available/000-default.conf
echo "</VirtualHost>" >> /etc/apache2/sites-available/000-default.conf
# Enable the site
ln -sf /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/000-default.conf
# Start Apache
apache2-foreground
EOF

RUN chmod +x /usr/local/bin/start-apache.sh

# Expose port 80 (Railway will map this to its assigned PORT)
EXPOSE 80

# Use the startup script instead of default CMD
CMD ["/usr/local/bin/start-apache.sh"]
