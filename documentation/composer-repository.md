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

## Known issues

Running a deployment can sometimes corrupt the Satis index, and it must be rebuilt manually. The logs should show something like

```
In JsonFile.php line 347:
"dist/all.json" does not contain valid JSON
Parse error on line 52780:
...} } }}ev": {
------------------^
Expected one of: 'EOF', '}', ',', ']'
```

Rebuild the index by calling `nohup php console.php app:rebuild > /tmp/nohup.out &` inside `webhook-server-*` container.

_NOTE_: Rebuilding can take up to 10 minutes.

