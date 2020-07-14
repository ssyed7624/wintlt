# Install php and apache v10
FROM php:7.2-apache

RUN apt-get update && apt-get install vim -y
RUN apt-get install -y mariadb-client

RUN \
  curl -L https://download.newrelic.com/php_agent/release/newrelic-php5-9.11.0.267-linux.tar.gz | tar -C /tmp -zx && \
  export NR_INSTALL_USE_CP_NOT_LN=1 && \
  export NR_INSTALL_SILENT=1 && \
  /tmp/newrelic-php5-*/newrelic-install install && \
  rm -rf /tmp/newrelic-php5-* /tmp/nrinstall* && \
  sed -i \
      -e 's/"REPLACE_WITH_REAL_KEY"/"e3bd88aab541048b5b5dfc30ec7096a50689NRAL"/' \
      -e 's/newrelic.appname = "PHP Application"/newrelic.appname = "b2b2c-app"/' \
      -e 's/;newrelic.daemon.app_connect_timeout =.*/newrelic.daemon.app_connect_timeout=15s/' \
      -e 's/;newrelic.daemon.start_timeout =.*/newrelic.daemon.start_timeout=5s/' \
      /usr/local/etc/php/conf.d/newrelic.ini

WORKDIR /var/www/html
COPY ./ ./
RUN mkdir -p storage/framework/views
RUN chmod 775 -R /var/www/html/
RUN chown -R www-data:www-data /var/www

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
RUN sed -i -e "s/^ *max_execution_time.*/max_execution_time = 180/g" /usr/local/etc/php/php.ini
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

RUN docker-php-ext-install pdo_mysql

RUN a2enmod rewrite headers

# php gd extension
RUN apt-get update && \
    apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev && \
    docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ && \
    docker-php-ext-install gd

# Installing cron package
RUN apt-get install -y cron

COPY docker-entrypoint.sh /
EXPOSE 80
ENTRYPOINT ["/docker-entrypoint.sh"]
