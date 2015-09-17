FROM php:5.6-cli

RUN apt-get update && apt-get install -y git zlib1g-dev

RUN git clone https://github.com/tarantool/tarantool-php.git /usr/src/php/ext/tarantool
RUN docker-php-ext-install zip tarantool

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer global require phpunit/phpunit:^4.8

env PATH ~/.composer/vendor/bin:$PATH
