FROM ghcr.io/city-of-helsinki/drupal-repository:dev

RUN apk add --no-cache php83-xdebug php83-dom php83-tokenizer php83-xml php83-xmlwriter
RUN { \
    echo '[xdebug]'; \
    echo 'zend_extension=xdebug.so'; \
    echo 'xdebug.mode=debug'; \
    echo 'xdebug.client_host=host.docker.internal'; \
    echo 'xdebug.idekey=PHPSTORM'; \
	} > /etc/php83/conf.d/50_xdebug.ini

WORKDIR /
COPY Makefile .env /
RUN make

WORKDIR $PROJECT_DIR
