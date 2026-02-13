FROM php:8.2-apache

RUN a2enmod rewrite
RUN docker-php-ext-install mysqli

WORKDIR /var/www/html

COPY . /var/www/html/
COPY start.sh /start.sh

RUN chmod +x /start.sh
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 8080

CMD ["/start.sh"]
