FROM php:apache
WORKDIR /var/www/cisco-switch-manager-gui

# variables
ENV WEBAPP_ROOT /var/www/cisco-switch-manager-gui
ENV APACHE_DOCUMENT_ROOT ${WEBAPP_ROOT}
ENV BACKUP_DIR /var/lib/cisco-switch-manager-gui/backups
ENV DATA_DIR /var/lib/cisco-switch-manager-gui/data

# set up apache
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    a2enmod rewrite

# install necessary PHP extensions and git for backup sync
RUN apt-get update && apt-get install -y \
    libssh2-1-dev \
    libssh2-1 \
    git \
    && pecl install ssh2 \
    && docker-php-ext-enable ssh2 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# create backup and data directories
RUN mkdir -p ${BACKUP_DIR} ${DATA_DIR} && chown www-data:www-data ${BACKUP_DIR} ${DATA_DIR}

# copy web app files
COPY . ${WEBAPP_ROOT}

# set permissions
RUN chown -R www-data:www-data ${WEBAPP_ROOT}

# start apache
CMD apache2-foreground
