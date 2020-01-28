FROM php:7-fpm-alpine

RUN apk add --no-cache --update git curl

# Development
RUN apk add --no-cache libxml2-dev $PHPIZE_DEPS \
  && pecl install xdebug-2.9.1 uopz \
  && docker-php-ext-enable xdebug uopz \
  && docker-php-ext-install xml soap curl

ENV LIBRARY_PATH=/lib:/usr/lib

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === trim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer

EXPOSE 9000

CMD ["php-fpm"]
