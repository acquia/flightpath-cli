# Drupal 9 Flight Path

This tool helps to build a Flight Path analysis report for migrating from
Drupal 7 to Drupal 9 with Acquia Migrate Accelerate.

This tool is built with Drutiny

## Installation
This tool is a Symfony console tool that can be installed with composer. You'll
need PHP 7.4 CLI or later and composer.

```
composer install
```

## Accessing your target Drupal 7 site
This tool is for assessing Drupal 7 sites. The tool accesses the site via a Drush
alias. We recommend using Drush 8 since it supports global site aliases.

```
composer global require drush/drush:^8.0
```
The Drupal 7 target may be run locally or remotely so long as remote Drupal sites
have their SSH configuration set inside the Drush alias.

List available site aliases:

```
drush site-alias
```

## Usage

```
drutiny
``
