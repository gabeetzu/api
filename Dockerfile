FROM php:8.2-apache

# Install system dependencies (NO GPU packages)
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    python3 \
    python3-pip \
    python3-venv \
    libgl1 \
    libglib2.0-0 \
    python3-opencv \
    && rm -rf /var/lib/apt/lists/*

# Python virtual environment
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Install Python packages with CPU-only PyTorch
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql

# Application files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html /opt/venv && \
    chmod 644 /var/www/html/best.pt && \
    mkdir -p /var/www/html/uploads && \
    chmod 777 /var/www/html/uploads

EXPOSE 80
