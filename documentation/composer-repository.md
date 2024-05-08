# Composer repository

A custom Composer repository used to distribute dependencies as part of [City-of-Helsinki/drupal-helfi-platform](https://github.com/City-of-Helsinki/drupal-helfi-platform) ecosystem.

To use this in your project, your `composer.json` should contain:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://repository.drupal.hel.ninja/"
    },
]
```

## Adding a new package to Composer repository

Your package must contain a valid `composer.json` file.

- Add your package to [satis.json](/satis.json) file.
- Add the required Webhook. See [Webhooks](#webhooks)

## Webhooks

In order for composer to figure out what packages have changed, the package index needs to be rebuilt on every commit.

Go to your GitHub repository's Settings -> Webhooks -> Add webhook

- Payload URL: `https://webhook.drupal.hel.ninja/hooks/update-index`
- Content type: `application/json`
- Events: `Send everything`
- Secret can be found on [Composer repository](https://helsinkisolutionoffice.atlassian.net/wiki/spaces/HEL/pages/6501891919/Composer+repository) confluence page.

## Development

You can rebuild the entire index by calling `php console.php queue:package ` inside `webhook-server-*` container. This will queue the index to be rebuilt.

_NOTE_: Rebuilding can take up to 10 minutes.

