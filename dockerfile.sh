#!/usr/bin/env bash

if [[ -z "$IMAGE" ]] ; then
    IMAGE='php:7.1-cli'
fi

if [[ -z "$TNT_CLIENT" ]] ; then
    TNT_CLIENT='pure'
fi

RUN_CMDS=''
RUN_POST_CMDS=''

if [[ $IMAGE == php* ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    docker-php-ext-install zip"
    if [[ $TNT_CLIENT == pure ]]; then
        RUN_POST_CMDS="$RUN_POST_CMDS && \\\\\n    composer require tarantool/client:@dev"
    else
        RUN_CMDS="$RUN_CMDS && \\\\\n    git clone https://github.com/tarantool/tarantool-php.git /usr/src/php/ext/tarantool"
        if [[ $IMAGE == php:7* ]]; then RUN_CMDS="$RUN_CMDS && \\\\\n    git --git-dir=/usr/src/php/ext/tarantool/.git --work-tree=/usr/src/php/ext/tarantool checkout php7-v2"; fi
        RUN_CMDS="$RUN_CMDS && \\\\\n    echo tarantool >> /usr/src/php-available-exts && docker-php-ext-install tarantool"
    fi
fi

if [[ $PHPUNIT_OPTS =~ (^|[[:space:]])--coverage-[[:alpha:]] ]]; then
    RUN_CMDS="$RUN_CMDS && \\\\\n    pecl install xdebug && docker-php-ext-enable xdebug"
fi

echo -e "
FROM $IMAGE

RUN apt-get update && \\
    apt-get install -y git curl zlib1g-dev${RUN_CMDS} && \\
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \\
    composer global require 'phpunit/phpunit:^4.8|^5.0|^6.0'${RUN_POST_CMDS}

ENV PATH=~/.composer/vendor/bin:\$PATH
ENV TARANTOOL_HOST=tarantool TARANTOOL_PORT=3301

CMD if [ ! -f composer.lock ]; then composer install; fi && ~/.composer/vendor/bin/phpunit\${PHPUNIT_OPTS:+ }\$PHPUNIT_OPTS
"
