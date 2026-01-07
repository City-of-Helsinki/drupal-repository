FROM ghcr.io/city-of-helsinki/drupal-repository:dev

RUN apk add --no-cache php84-xdebug php84-dom php84-tokenizer php84-xml php84-xmlwriter
RUN { \
    echo '[xdebug]'; \
    echo 'zend_extension=xdebug.so'; \
    echo 'xdebug.mode=debug'; \
    echo 'xdebug.client_host=host.docker.internal'; \
    echo 'xdebug.idekey=PHPSTORM'; \
	} > /etc/php84/conf.d/50_xdebug.ini

WORKDIR /
COPY Makefile .env /
RUN make

WORKDIR $PROJECT_DIR
