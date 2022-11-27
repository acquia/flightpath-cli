# Drupal 9 Flight Path (Beta)

This tool builds a Flight Path analysis report for migrating from
Drupal 7 to Drupal 9 and shows how Acquia Migrate Accelerate could help.

### :warning: This is a developer tool
If you are a Drupal 7 site owner looking to get a Flight Path report, please
either [contact Acquia to request a report](https://www.acquia.com/products/drupal-cloud/acquia-migrate-accelerate/flight-path)
or speak with your Drupal development team.


To be able to use this tool you'll need to learn how to set it up and use it to
access your Drupal 7 site (where it is hosted). You should have an understanding of PHP, SSH, Drush and optionally Docker (if you're using a docker based
technology).


This tool is powered by [Drutiny](https://github.com/drutiny/drutiny).

# Prerequisites

To use this tool you'll need:

-   A unix CLI environment (OSX (Mac) or Linux)
-   [PHP 7.4](https://formulae.brew.sh/formula/php@7.4) - If you're using a Mac, we recommend installing PHP with [Homebrew](https://brew.sh).
    See [installing Ubuntu on Windows 10](https://ubuntu.com/tutorials/ubuntu-on-windows).
-   [Drush](https://docs.drush.org/en/8.x/install/) (version 8 recommended. You must have a [version of drush compatible with Drupal 7](https://www.drush.org/latest/install/#drupal-compatibility))

If you want to leverage faster audits with parallel processing, be sure to install the [PCNTL extension](https://www.php.net/manual/en/book.pcntl.php) where you’re using the Flightpath tool.

:point_up: **Note**: This tool recommends PHP 7.4 because its most compatible with Drupal 7 sites.

## Installation

### Option One: From Source (with composer)

You can build from source with composer:

    composer install

Once installed, you'll run flightpath from within the root of the project.

    ./flightpath --version

### Option Two: From Phar

Download the latest Phar file from the [releases page](https://github.com/acquia/flightpath-cli/releases).
We recommend you place this inside your CLI path.

    mv flightpath-<version>.phar /usr/local/bin/flightpath

Once installed you should be able to access it from anywhere in your terminal:

    flightpath --version

## Setting up Acquia Cloud Plugin

If you're auditing a site on Acquia Cloud, you may need to setup flightpath
with Acquia Cloud API credentials to pull connectivity information.

If you're already using [Acquia CLI](https://github.com/acquia/cli) then
flightpath will discover those credentials on your system and use those.

If you're not using Acquia CLI, then you'll need to generate API keys in
Acquia Cloud and provide them to flightpath through the `plugin:setup` command.

To do this, first generate an [API key from
Acquia Cloud](https://docs.acquia.com/cloud-platform/develop/api/auth/#cloud-generate-api-token). Then install your API credentials into the flightpath tool using `plugin:setup`.

    flightpath plugin:setup acquia:cloud

Follow the prompts to provide your key and secret. Then you'll be able to reach
Drupal 7 sites hosted on Acquia Cloud.

## Accessing your target Drupal 7 site

There are a variety of ways to target Drupal 7 sites:

-   Using local Drush and drush aliases
-   Accessing local sites powered by [DDEV](https://ddev.readthedocs.io/en/stable/)
-   Accessing local sites powered by [Lando](https://docs.lando.dev/)
-   Accessing sites hosted on Acquia Cloud
-   Accessing sites hosted on Pantheon
-   Accessing local or remote sites with [Drush aliases](https://www.drush.org/latest/site-aliases/)

*__Note__: this tool does have a driver for Docksal but we've found it to not work well
and do not recommend using it.*

Each access method uses a different **target** provider. Use the
`target:sources` command to see which sources are available. Use
`target:list <source>` command to see the targets available by the
provided source.

    # List all target sources:
    flightpath target:sources

    # Show all available drush aliases:
    flightpath target:list drush

If you already use Drush, for example, to access local and remote sites
using Drush aliases, then flightpath can use drush as an access method
to perform its assessments. Note Acquia and Pantheon target sources are
also available.

    # Download Drush Aliases from Acquia with Acquia CLI
    acli remote:aliases:download
    # (choose php instead of yml so it is compatible with Drupal 7 sites.)

    # Download Drush Aliases from Pantheon with Terminus
    terminus aliases

-   [Get started with Acquia CLI](https://docs.acquia.com/acquia-cli/)
-   [Get started with Terminus](https://pantheon.io/docs/terminus)

__Tip__: If you have trouble with Drush aliases working, try using Drush version 8 since it supports global site aliases for Drupal 7.

    composer global require drush/drush:^8.0 --with-dependencies

The Drupal 7 target may be run locally or remotely so long as remote
Drupal sites have their SSH configuration set inside the Drush alias.

## Usage

[Watch a demo on how to use flightpath](https://drive.google.com/file/d/1Zl_8oUPvO_iphqRR1hHUwQhEFEPntyxH/view?usp=sharing).

    flightpath profile:run ama_flight_path <target ref> --format=html

This will generate the flightpath report for the given site in HTML
format.

**Note: If you do not set the format to HTML, then the output will be directly to the console.**

## Multisite

You can build reports for each site in a multisite by using the `--uri` option
to specify the URI for each site in the Drupal instance.

    flightpath profile:run ama_flight_path <target ref> --format=html --uri=www.siteA.com --uri=www.siteB.com --uri=....

## Known Issues

### Prefixed Tables
Drupal sites that prefix database tables __will not work__. Flight Path relies on drush tools like `sqlq` which do not support database prefixing. As such these policies fail to yield results and can prevent the entire report from generating.

__Workaround__: Remove table prefixes
See example in stackoverflow with MySQL: https://stackoverflow.com/questions/6404158/how-to-remove-a-prefix-name-from-every-table-name-in-a-mysql-database

It is not recommended to rename tables in production. Instead, this maybe something done in a local environment.

### Docksal support
Docksal containers for Drupal 7 appear to be broken which impede the ability to
audit the site. Please ensure your Drupal 7 sites are functioning inside Docksal
before attempting to conduct a flightpath audit.

Ensure a Docksal alias is setup and is the same name as the folder directory.
Inside the web container, rename drush8 to drush. Flightpath does not know how to
find drush when it is called drush8.


## Troubleshooting

### Undefined index: 'services' in DDEV
Please ensure you are running DDEV v1.18 or later. Confirmed this issue appeared
in DDEV 1.17 (fixed when upgraded to 1.19 but should work on 1.18 too).
Drush/PHP Error on target site
Flightpath may spit out PHP errors when it fails to run correctly. Pay attention 
to the file paths in the erroneous output to determine if the error occurred
locally where flightpath is being executed from or remotely from within the
Drupal bootstrap process.

Flightpath errors will come from phar file sources whilst Drupal errors will result from absolute file paths on the target server.

When the errors are related to the Drupal site, its an indication that the way
Flightpath invoked Drush didn’t correctly engage with Drupal. An example of when
this happens is in a multisite install where the default install location has no
database connectivity. In this situation, its critical to supply a URI parameter (`--uri`)
to ensure Flightpath gives drush the right parameters to bootstrap into a Drupal
site on the multisite codebase.


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
