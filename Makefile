-include .env

COMPOSER := $(shell which composer.phar 2>/dev/null || which composer 2>/dev/null)
COMPOSER_AUTH = ${COMPOSER_HOME}/auth.json

$(COMPOSER_AUTH):
	@composer -g config github-oauth.github.com ${GITHUB_OAUTH}
