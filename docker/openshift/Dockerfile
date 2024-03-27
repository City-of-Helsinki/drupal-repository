FROM ghcr.io/city-of-helsinki/drupal-repository:latest

COPY / /var/www/html/
COPY hooks.json /etc/webhook/hooks.json

# Copy cron scripts
RUN mkdir /crons
COPY docker/openshift/crons/ /crons
RUN chmod +x /crons/*

WORKDIR /var/www/html
RUN make && \
  chmod a+rw $COMPOSER_HOME -R && \
  composer install --no-progress --prefer-dist --no-interaction --no-dev --optimize-autoloader
