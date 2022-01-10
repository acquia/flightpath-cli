# Drupal 9 Flight Path

This tool helps to build a Flight Path analysis report for migrating from
Drupal 7 to Drupal 9 with Acquia Migrate Accelerate.

This tool is built with Drutiny.

## Installation
This tool is a Symfony console tool that can be installed with
composer. You'll need PHP 7.4 CLI or later and composer.

```
composer install
```

## Accessing your target Drupal 7 site
This tool is for assessing Drupal 7 sites. There are a variety of ways to target them:
- Using local Drush and drush aliases
- Accessing local sites powered by [DDEV](https://ddev.readthedocs.io/en/stable/)
- Accessing sites hosted on Acquia Cloud
- Accessing sites hosted on Pantheon

Each access method uses a different **target** provider. Use the
`target:sources` command to see which sources are available. Use
`target:list <source>` command to see the targets available by the
provided source.

```
# List all target sources:
./flightpath target:sources

# Show all available drush aliases:
./flightpath target:list drush
```

If you already use Drush, for example, to access local and remote sites
using Drush aliases, then this tool can piggyback on that access method
to perform its assessments.

```
# Download Drush Aliases from Acquia with Acquia CLI
acli remote:aliases:download

# Download Drush Aliases from Pantheon with Terminus
terminus aliases

```

- [Get started with Acquia CLI](https://docs.acquia.com/acquia-cli/)
- [Get started with Terminus](https://pantheon.io/docs/terminus)

Tip: If you have trouble with Drush aliases working, try using Drush version 8 since it supports global site aliases.

```
composer global require drush/drush:^8.0
```
The Drupal 7 target may be run locally or remotely so long as remote
Drupal sites have their SSH configuration set inside the Drush alias.

## Usage

```
./flightpath profile:run ama_flight_path <target> --format=html
```

This will generate the flightpath report for the given site in HTML
format.

__Note: If you do not set the format to HTML, then the output will be directly to the console.__

## Multisite
You can build reports for each site in a multisite by using the `--uri` option
to specify the URI for each site in the Drupal instance.

```
./flightpath profile:run ama_flight_path <target> --format=html --uri=www.siteA.com --uri=www.siteB.com --uri=....
```
