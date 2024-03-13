FROM ghcr.io/city-of-helsinki/drupal-repository:dev

RUN apk add --no-cache php82-xdebug php82-dom php82-tokenizer php82-xml php82-xmlwriter
RUN { \
    echo '[xdebug]'; \
    echo 'zend_extension=xdebug.so'; \
    echo 'xdebug.mode=debug'; \
    echo 'xdebug.client_host=host.docker.internal'; \
    echo 'xdebug.idekey=PHPSTORM'; \
	} > /etc/php82/conf.d/50_xdebug.ini
