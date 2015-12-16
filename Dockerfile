FROM php:5.6

ADD / /graviton-import-export/

RUN apt-get update  && \
    apt-get -y install libssl-dev git && \
    apt-get clean && \
    pecl install mongo && \
    echo 'extension=mongo.so' > /usr/local/etc/php/conf.d/mongo.ini && \
    docker-php-ext-install zip && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    cd /graviton-import-export && \
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress && \
    apt-get -y remove git libssl-dev && \
    apt-get -y autoremove && \
    rm -rf /tmp/pear /var/lib/apt/lists/* /usr/local/bin/composer /root/.composer

ENTRYPOINT ["php", "/graviton-import-export/bin/graviton-import-export"]
