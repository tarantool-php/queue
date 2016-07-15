#!/usr/bin/env bash

if [[ -z "$PHP_RUNTIME" ]] ; then
    PHP_RUNTIME='php:5.6-cli'
fi

RUN_CMDS=''

if [[ $PHP_RUNTIME == php* ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    git clone https://github.com/tarantool/tarantool-php.git /usr/src/php/ext/tarantool"
    RUN_CMDS="$RUN_CMDS && \\\\\n    echo tarantool >> /usr/src/php-available-exts && docker-php-ext-install zip tarantool"
fi

if [[ $PHPUNIT_OPTS =~ (^|[[:space:]])--coverage-[[:alpha:]] ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    git clone https://github.com/xdebug/xdebug.git /usr/src/php/ext/xdebug"
    RUN_CMDS="$RUN_CMDS && \\\\\n    echo xdebug >> /usr/src/php-available-exts && docker-php-ext-install xdebug"
fi

echo -e "
FROM $PHP_RUNTIME

RUN apt-get update && \\
    apt-get install -y git curl zlib1g-dev${RUN_CMDS} && \\
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \\
    composer global require 'phpunit/phpunit:^4.8|^5.0'

ENV PATH=~/.composer/vendor/bin:\$PATH
ENV TARANTOOL_HOST=tarantool TARANTOOL_PORT=3301

CMD if [ ! -f composer.lock ]; then composer install; fi && ~/.composer/vendor/bin/phpunit\${PHPUNIT_OPTS:+ }\$PHPUNIT_OPTS
"
