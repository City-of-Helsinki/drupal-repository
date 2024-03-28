# Development

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

## Test webhooks locally

Base URL to Webhook server: https://helfi-webhook.docker.so

1. Copy payload body from `Recent deliveries` webhook tab on GitHub (Settings -> Webhooks -> Edit -> Recent deliveries). For example:
2. Save the request body to a `body.json` file
3. Generate an X-Hub-Signature for your request body: `php -r "print hash_hmac('sha1', file_get_contents('body.json'), '{your webhook secret key here}');"`
4. Send the request using CURL

### Update index webhook

Example payload body:
```json
{
    "repository": {
        "full_name": "City-of-Helsinki/drupal-helfi-platform-config"
    }
}
```

Example request: 
```bash
curl -i \
    -H 'Content-Type: application/json' \
    -H "X-Hub-Signature: sha1={your hash_hmac from previous step }" \
    -X POST https://helfi-webhook.docker.so/hooks/update-index \
    --data-binary "@body.json"
```


### Update release note webhook

Example payload body:

@todo

Example request:
```bash
curl -i \
    -H 'Content-Type: application/json' \
    -H "X-Hub-Signature: sha1={your hash_hmac from previous step }" \
    -X POST https://helfi-webhook.docker.so/hooks/update-release-note \
    --data-binary "@body.json"
```

### Update automation pull request webhook

Example payload body:

@todo

Example request:
```bash
curl -i \
    -H 'Content-Type: application/json' \
    -H "X-Hub-Signature: sha1={your hash_hmac from previous step }" \
    -X POST https://helfi-webhook.docker.so/hooks/update-automation-pull-request \
    --data-binary "@body.json"
```

### Trigger dispatch webhook

Example payload body:

@todo

Example request:
```bash
curl -i \
    -H 'Content-Type: application/json' \
    -H "X-Hub-Signature: sha1={your hash_hmac from previous step }" \
    -X POST https://helfi-webhook.docker.so/hooks/trigger-dispatch \
    --data-binary "@body.json"
```
