# Drupal Composer repository

This is composer repository used to distribute dependencies as part of [City-of-Helsinki/drupal-helfi-platform](https://github.com/City-of-Helsinki/drupal-helfi-platform) ecosystem.

To use this, your `composer.json` should contain:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://repository.drupal.hel.ninja/"
    },
]
```

## Adding a new package 

Your package must contain a `composer.json` file.

- Add your package to [satis.json](/satis.json) file.
- Add the required webhook: [Update composer repository](#update-composer-repository).

## Available webhooks 

### Update composer repository

In order for composer to figure out what packages have changed, the package index needs to be rebuilt on every commit.

Go to Settings -> Webhooks -> Add webhook

- Payload URL: `https://webhook.drupal.hel.ninja/hooks/update-index`
- Content type: `application/json`
- Events: `Send everything`
- Secret can be found on [Composer repository](https://helsinkisolutionoffice.atlassian.net/wiki/spaces/HEL/pages/6501891919/Composer+repository) confluence page.

## Known issues

Running a deployment can sometimes corrupt the Satis database and it must be rebuild manually. The logs should show something like 

```
In JsonFile.php line 347:
"dist/all.json" does not contain valid JSON
Parse error on line 52780:
...} } }}ev": {
------------------^
Expected one of: 'EOF', '}', ',', ']'
```

Rebuild the index by calling `php console.php app:rebuild` inside Webhook container. 

_NOTE_: Rebuilding can take up to 10 minutes.

## Documentation

See [documentation](/documentation) for more documentation.

## Contact

Slack: #helfi-drupal (http://helsinkicity.slack.com/)
