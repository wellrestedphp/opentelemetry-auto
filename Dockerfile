FROM php:8.1-cli

ENV PATH="${PATH}:/app/vendor/bin"

RUN DEBIAN_FRONTEND=noninteractive \
  apt-get update && \
  apt-get -y install \
    dumb-init \
    gettext \
    libssl-dev \
    unzip \
    wget \
    zip \
  && rm -rf /var/lib/apt/lists/*

# Open Telemetry
RUN pecl install -o -f opentelemetry \
    && docker-php-ext-enable opentelemetry

# Xdebug
RUN pecl install xdebug \
  && docker-php-ext-enable xdebug

# Install Composer.
RUN curl -sS https://getcomposer.org/installer | php -- \
  --filename=composer --install-dir=/usr/local/bin

# Create a directory for project sources and user's home directory
RUN mkdir /app && \
  chown -R www-data:www-data /app && \
  chown -R www-data:www-data /var/www

# Copy XDebug config file
COPY ./xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

ENTRYPOINT ["dumb-init", "--"]

WORKDIR /app

USER www-data
