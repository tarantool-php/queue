#!/usr/bin/env bash

if [[ -z "$IMAGE" ]] ; then
    IMAGE='php:7.2-cli'
fi

if [[ -z "$TNT_CLIENT" ]] ; then
    TNT_CLIENT='pure'
fi

if [[ $TNT_CLIENT == pecl ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    git clone https://github.com/tarantool/tarantool-php.git /usr/src/php/ext/tarantool"
    RUN_CMDS="$RUN_CMDS && \\\\\n    git --git-dir=/usr/src/php/ext/tarantool/.git --work-tree=/usr/src/php/ext/tarantool checkout php7-v2"
    RUN_CMDS="$RUN_CMDS && \\\\\n    echo tarantool >> /usr/src/php-available-exts && docker-php-ext-install tarantool"
    COMPOSER_REMOVE='tarantool/client'
fi

if [[ $PHPUNIT_OPTS =~ (^|[[:space:]])--coverage-[[:alpha:]] ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    pecl install xdebug && docker-php-ext-enable xdebug"
fi

if [[ "1" != "$CHECK_CS" ]]; then
    COMPOSER_REMOVE="$COMPOSER_REMOVE friendsofphp/php-cs-fixer"
fi

echo -e "
FROM $IMAGE

RUN apt-get update && \\
    apt-get install -y git curl libzip-dev && \\
    docker-php-ext-configure zip --with-libzip && \\
    docker-php-ext-install zip${RUN_CMDS}

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

ENV PATH=~/.composer/vendor/bin:\$PATH
ENV TARANTOOL_HOST=tarantool TARANTOOL_PORT=3301

CMD if [ ! -f composer.lock ]; then ${COMPOSER_REMOVE:+composer remove --dev --no-update }$COMPOSER_REMOVE${COMPOSER_REMOVE:+ && }composer install; fi && \\
    vendor/bin/phpunit\${PHPUNIT_OPTS:+ }\$PHPUNIT_OPTS
"
