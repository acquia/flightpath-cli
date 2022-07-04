# Drupal 9 Flight Path

This tool helps to build a Flight Path analysis report for migrating from
Drupal 7 to Drupal 9 with Acquia Migrate Accelerate.

This tool is built with Drutiny.

# Prerequisites

To use this tool you'll need:

-   A unix CLI environment (OSX (Mac) or Linux)
-   [PHP 7.4](https://formulae.brew.sh/formula/php@7.4) - If you're using a Mac, we recommend installing PHP with [Homebrew](https://brew.sh).
    See [installing Ubuntu on Windows 10](https://ubuntu.com/tutorials/ubuntu-on-windows).
-   [Drush](https://docs.drush.org/en/8.x/install/) (version 8 recommended. You must have a [version of drush compatible with Drupal 7](https://www.drush.org/latest/install/#drupal-compatibility))

## Installation

### Option One: From Source (with composer)

If you have access to the source code, you can build from source with composers

    composer install

Once installed, you'll run flightpath from only within the root of the project.

    ./flightpath --version

### Option Two: From Phar

Download the latest Phar file from the [releases page](https://github.com/acquia/ama-report-builder/releases)
or from the version provided to you by Acquia. We recommend you place this inside
your CLI path.

    mv flightpath-<version>.phar /usr/local/bin/flightpath

Once installed you should be able to access it from anywhere in your terminal:

    flightpath --version

## Setup Acquia Cloud Plugin

The Acquia Cloud plugin for this tool requires an API key which without
may block some functionality. Please ensure you've generated an [API key from
Acquia Cloud](https://docs.acquia.com/cloud-platform/develop/api/auth/#cloud-generate-api-token). Install your API credentials into the flightpath tool using `plugin:setup`.

    flightpath plugin:setup acquia:cloud

## Accessing your target Drupal 7 site

This tool is for assessing Drupal 7 sites. There are a variety of ways to target them:

-   Using local Drush and drush aliases
-   Accessing local sites powered by [DDEV](https://ddev.readthedocs.io/en/stable/)
-   Accessing local sites powered by [Lando](https://docs.lando.dev/)
-   Accessing sites hosted on Acquia Cloud
-   Accessing sites hosted on Pantheon
-   Accessing local or remote sites with [Drush aliases](https://www.drush.org/latest/site-aliases/)

Each access method uses a different **target** provider. Use the
`target:sources` command to see which sources are available. Use
`target:list <source>` command to see the targets available by the
provided source.

    # List all target sources:
    flightpath target:sources

    # Show all available drush aliases:
    flightpath target:list drush

If you already use Drush, for example, to access local and remote sites
using Drush aliases, then this tool can piggyback on that access method
to perform its assessments. Note Acquia and Pantheon target sources are
also available.

    # Download Drush Aliases from Acquia with Acquia CLI
    acli remote:aliases:download
    # (choose php instead of yml so it is compatible with Drupal 7 sites.)

    # Download Drush Aliases from Pantheon with Terminus
    terminus aliases

-   [Get started with Acquia CLI](https://docs.acquia.com/acquia-cli/)
-   [Get started with Terminus](https://pantheon.io/docs/terminus)

Tip: If you have trouble with Drush aliases working, try using Drush version 8 since it supports global site aliases for Drupal 7.

    composer global require drush/drush:^8.0 --with-dependencies

The Drupal 7 target may be run locally or remotely so long as remote
Drupal sites have their SSH configuration set inside the Drush alias.

## Usage

    flightpath profile:run ama_flight_path <target ref> --format=html

This will generate the flightpath report for the given site in HTML
format.

**Note: If you do not set the format to HTML, then the output will be directly to the console.**

## Multisite

You can build reports for each site in a multisite by using the `--uri` option
to specify the URI for each site in the Drupal instance.

    flightpath profile:run ama_flight_path <target ref> --format=html --uri=www.siteA.com --uri=www.siteB.com --uri=....

## Troubleshooting

### PHP Memory Exhaustion

This could be because your PHP-CLI memory limit is set to low. You should try
increasing it to 1024 MB. Alternately you can run PHP without a memory limit:

    php -d memory_limit=-1 /path/to/flightpath profile:run ama_flight_path <target ref> --format=html --uri=www.siteA.com

### Phar file not working

Environments may have configrations that prohibit the use of phar files. If so,
you might like to first [extract the phar file](https://stackoverflow.com/questions/12997385/extracting-files-from-phar-archive) and use it from source instead.

Alternatively, if you're using Suhosin, you may want to ensure phars are on the allow list:

    suhosin.executor.include.whitelist = phar

See <https://stackoverflow.com/questions/19925526/using-cli-to-use-phar-file-not-working>
