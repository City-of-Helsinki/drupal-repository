# Documentation

Run `php console.php` to see available console commands.
See [hooks.json](/hooks.json) to see what command is called for each action.

## Production Docker image

See https://github.com/City-of-Helsinki/drupal-docker-images/tree/main/openshift/drupal-repository for more information about the underlying Docker image.

## Available webhooks

- [Automatic changelog generation](/documentation/automatic-changelog.md)
- [Update composer repository](/documentation/composer-repository.md#webhooks)

## Required environment variables

```
GITHUB_OAUTH=your-github-oauth-token
# This is used to update individual packages (satis rebuilds)
WEBHOOK_SECRET=your-webhook-secret
# This is used by this repository to trigger GitHub actions
WEBHOOK_UPDATE_SECRET=your-webhook-secret
```

## Local development

To start the application, run:

- `docker compose up -d`

To rebuild the locale Docker image, run:

- `docker compose build`

and restart the project: `docker compose stop && docker compose up -d`

URL to Satis/Composer index: https://helfi-repository.docker.so

### Test webhooks locally

URL to Webhook server: https://helfi-webhook.docker.so

1. Copy request body from `Recent deliveries` tab of your repository
2. Save the request body to a `body.json` file
3. Generate a X-Hub-Signature for your request body: `php -r "print hash_hmac('sha1', file_get_contents('body.json'), '{your webhook secret key here}');"`
4. Send the request: `curl -i -H 'Content-Type: application/json' -H "X-Hub-Signature: sha1={your hash_hmac from previous step }" -X POST https://helfi-webhook.docker.so/hooks/update-release-note --data-binary "@body.json"`
