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

## Publication times

Every package version has a `published-time` field, so projects can use Composer's [cooldown](https://getcomposer.org/doc/06-config.md#cooldown) setting with this repository.

Satis sets it to the time a version first appears in a build and reads it back from the previous build's `dist/p2` files. This is why the `dist` directory must persist between builds: if it's lost, every version is treated as newly published and held back for the cooldown period.

## Webhooks

In order for composer to figure out what packages have changed, the package index needs to be rebuilt on every commit.

Go to your GitHub repository's Settings -> Webhooks -> Add webhook

- Payload URL: `https://webhook.drupal.hel.ninja/hooks/update-index`
- Content type: `application/json`
- Events: `Send everything`
- Secret can be found on [Composer repository](https://helsinkisolutionoffice.atlassian.net/wiki/spaces/HEL/pages/6501891919/Composer+repository) confluence page.

## Development

You can rebuild the entire index by calling `php console.php queue:package all` inside `webhook-server-*` container. This will queue the index to be rebuilt.

_NOTE_: Rebuilding can take up to 10 minutes.

