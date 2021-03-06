PHP := php -dmemory_limit=-1
SATIS := vendor/bin/satis
COMPOSER := $(shell which composer.phar 2>/dev/null || which composer 2>/dev/null)

.PHONY := update-repository docker-build docker-attach

default: dist

docker-build:
	docker-compose build --no-cache
	docker-compose stop
	docker-compose up -d

docker-attach:
	docker-compose exec app sh

update-repository:
	git checkout .
	git clean -f
	git pull

dist: $(SATIS) Makefile satis.json
	composer -g config github-oauth.github.com ${GITHUB_OAUTH}
	$(PHP) $(SATIS) build satis.json dist

$(SATIS): composer.lock
	$(PHP) $(COMPOSER) install
	touch $@
