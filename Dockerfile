FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    python3 \
    python3-pip \
    python3-venv \
    libgl1 \
    libglib2.0-0 \
    python3-opencv

RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"
COPY requirements.txt .
RUN pip install -r requirements.txt

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html /opt/venv \
    && mkdir -p /var/www/html/uploads \
    && chmod 777 /var/www/html/uploads

EXPOSE 80
