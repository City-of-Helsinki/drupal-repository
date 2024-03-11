# Automatic changelog generation

The CLI tool fetches project's `composer.lock` for given `{head}` and `{base}` commits, then compares what modules have changed and uses the [Generate release notes content for a release](https://docs.github.com/en/rest/releases/releases#generate-release-notes-content-for-a-release) API to generate a changelog for each module.

The changelog generation is limited to modules with `whitelisted` set to `true` in [satis.json](/satis.json).

## Generate a changelog for project releases

See https://github.com/City-of-Helsinki/drupal-helfi-kymp/releases/tag/2024-02-28.1 for an example.

Takes a `{base}` release as an argument and fetches the previous release automatically as `{head}`, then compares what's changed between the two and updates the release body field automatically on GitHub.

The values are parsed automatically from Release event's payload body by Webhook server. See `update-release-note` hook in [hooks.json](/hooks.json).

To test this locally, run: `php console.php changelog:project-release --project {project name} --base {the newer release} --head {the previous release}`

The `{project name}` is the combination of `username` and `repository`, separated by `/` from `$projects` array in [console.php](/console.php). For example `city-of-helsinki/drupal-helfi-kymp`.

### Using this in your project

Make sure your project is listed in `$projects` array in [console.php](/console.php), and has `changelog = true` setting.

Go to your GitHub repository's Settings -> Webhooks -> Add webhook

- Payload URL: `https://webhook.drupal.hel.ninja/hooks/update-release-note`
- Content type: `application/json`
- See [Contact](#contact) for secret (`WEBHOOK_UPDATE_SECRET`).
- Select individual events: `Releases`. **Please remember to unselect all other events**.

## Generate a changelog for pull requests

This is currently only triggered for [Automatic updates](https://github.com/City-of-Helsinki/drupal-helfi-platform/blob/main/documentation/automatic-updates.md) pull requests.

Generates a changelog for changes between `dev` and `update-config` branches and updates the Automation pull request body field automatically on GitHub.

The values are parsed automatically from Pull request event's payload body by Webhook server. See `update-automation-pull-request` hook in [hooks.json](/hooks.json).

To test this locally, run: `php console.php changelog:automatin-pull-request --project {project name} --base {the newer release} --head {the previous release} --number {the number of the pull request to update} `

The `{project name}` is the combination of `username` and `repository`, separated by `/` from `$projects` array in [console.php](/console.php). For example `city-of-helsinki/drupal-helfi-kymp`.

### Using this in your project

Make sure your project is listed in `$projects` array in [console.php](/console.php), and has `changelog = true` setting.

Go to your GitHub repository's Settings -> Webhooks -> Add webhook

- Payload URL: `https://webhook.drupal.hel.ninja/hooks/update-automation-pull-request`
- Content type: `application/json`
- See [Contact](#contact) for secret (`WEBHOOK_UPDATE_SECRET`).
- Select individual events: `Pull requests`. **Please remember to unselect all other events**.

