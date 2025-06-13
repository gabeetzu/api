FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    python3 \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql

# Install Python dependencies for CNN processing
RUN pip3 install ultralytics pillow torch torchvision

# Copy application files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Set proper permissions
RUN chmod -R 755 /var/www/html \
    && chown -R www-data:www-data /var/www/html

# Create uploads directory with proper permissions
RUN mkdir -p /var/www/html/uploads \
    && chmod 777 /var/www/html/uploads

# Expose port
EXPOSE 80
