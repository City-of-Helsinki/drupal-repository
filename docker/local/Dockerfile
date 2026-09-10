FROM ghcr.io/city-of-helsinki/drupal-repository:dev

RUN apk add --no-cache php85-xdebug php85-dom php85-tokenizer php85-xml php85-xmlwriter
RUN { \
    echo '[xdebug]'; \
    echo 'zend_extension=xdebug.so'; \
    echo 'xdebug.mode=debug'; \
    echo 'xdebug.client_host=host.docker.internal'; \
    echo 'xdebug.idekey=PHPSTORM'; \
	} > /etc/php85/conf.d/50_xdebug.ini

WORKDIR /
COPY Makefile .env /
RUN make

WORKDIR $PROJECT_DIR
