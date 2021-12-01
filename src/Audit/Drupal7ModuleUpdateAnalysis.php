<?php

namespace Drutiny\Acquia\AMA\Audit;

use Drutiny\Sandbox\Sandbox;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Drutiny\Audit\Drupal\ModuleAnalysis;

/**
 * Generic module is enabled check.
 *
 */
class Drupal7ModuleUpdateAnalysis extends ModuleAnalysis
{

  /**
   *
   */
    public function gather(Sandbox $sandbox)
    {
        // Gather module data from drush pm-list.
        parent::gather($sandbox);

        $modules = $this->get('modules');
        $modules = $this->getModuleFilepathData($modules);
        $modules = $this->getDecoratedModuleData($modules);

        $installed = array_filter($modules, function ($module) {
          return $module['status'] != 'Not installed';
        });

        $github_creds = $this->container->get('Drutiny\Plugin\GithubPlugin')->load();
        $client = $this->container->get('http.client')->create();
        $response = $client->request('GET', 'https://raw.githubusercontent.com/acquia/acquia-migrate-recommendations/master/recommendations.json', [
          'headers' => [
            'User-Agent' => 'drutiny-ama',
            //'Accept' => 'application/vnd.github.v3+json',
            'Accept-Encoding' => 'gzip',
            'Authorization' => 'token ' . $github_creds['personal_access_token']
          ]
        ]);
        $recommendations = json_decode($response->getBody(), true);

        $vetted = $unvetted = $unknown = [];
        foreach ($recommendations['data'] as $rule) {
          if (!isset($rule['replaces'])) {
            continue;
          }
          if (!isset($installed[$rule['replaces']['name']])) {
            continue;
          }
          if ($rule['vetted']) {
            $vetted[] = $rule['replaces']['name'];
            $modules[$rule['replaces']['name']]['strategy'] = 'ama_vetted';
          }
          else {
            $unvetted[] = $rule['replaces']['name'];
            $modules[$rule['replaces']['name']]['strategy'] = 'ama_unvetted';
          }
        }
        $this->set('vetted', $vetted);
        $this->set('unvetted', $unvetted);
        $this->set('unknown', array_diff(array_keys($installed), $vetted, $unvetted));
        $this->set('modules', $modules);
    }

    /**
     * Decorate module data with filepath metadata.
     */
    protected function getModuleFilepathData($modules):array
    {
      // Get the locations of all the modules in the codebase.
      $filepaths = $this->target->getService('exec')->run('find $DRUSH_ROOT -name \*.info -type f', function ($output) {
        return array_map(function ($line) {
          return trim($line);
        }, explode(PHP_EOL, $output));
      });

      $module_filepaths = [];

      foreach ($filepaths as $filepath) {
        list($module_name, , ) = explode('.', basename($filepath));
        $module_filepaths[$module_name] = $filepath;
      }

      foreach($modules as $module => &$info) {
        $info['filepath'] = $module_filepaths[$module];
        $info['dirname'] = dirname($info['filepath']);
        $info['name'] = $module;
        switch (true) {
          case strpos($info['filepath'], $this->target['drush.root'] . '/modules')  !== false:
            $info['type'] = 'core';
            break;

          case strpos($info['filepath'], 'modules/contrib')  !== false:
            $info['type'] = 'contrib';
            break;

          case strpos($info['filepath'], 'modules/custom')  !== false:
            $info['type'] = 'custom';
            break;

          // Defaulting to contrib will check for existance of the module
          // as the default behaviour.
          default:
            $info['type'] = 'contrib';
            break;
        }
      }
      return $modules;
    }

    /**
     * Get decorated module data.
     */
    protected function getDecoratedModuleData($modules):array
    {
      foreach ($modules as $module => $info) {

        // If the module is embedded inside another project then its a sub-module.
        $parent_modules = array_filter($modules, function ($mod) use ($info) {
          if ($info['name'] == $mod['name']) {
            return false;
          }
          return strpos($info['filepath'], $mod['dirname'] . '/') !== false;
        });

        if (count($parent_modules)) {
          $modules[$module]['type'] = 'sub-module';
          $modules[$module]['parent'] = reset($parent_modules)['name'];
          $modules[$modules[$module]['parent']]['sub-modules'][] = $modules[$module]['name'];
        }

        $modules[$module]['upgrade_path'] = false;

        if ($modules[$module]['type'] == 'contrib') {

          $has_d7_versions = $this->getVersions($module, '7.x');
          $has_current_versions = $this->getVersions($module, 'current');

          if (!$has_d7_versions && !$has_current_versions) {
            $modules[$module]['type'] = 'custom';
          }

          if ($has_current_versions) {
            $modules[$module]['upgrade_path'] = true;
          }
        }
      }
      return $modules;
    }

    protected function getVersions($project, $version = 'current')
    {
      $url = strtr('https://updates.drupal.org/release-history/%module%/%version%', [
        '%module%' => $project,
        '%version%' => $version
      ]);
      $history = $this->getUpdates($url);

      // No release history was found.
      if (!is_array($history)) {
        return false;
      }

      return $history;
    }

    protected function getUpdates($url) {
      return $this->runCacheable($url, function () use ($url) {
        $client = $this->container->get('http.client')->create();
        $response = $client->request('GET', $url);

        if ($response->getStatusCode() != 200) {
          return false;
        }

        return $this->toArray(simplexml_load_string($response->getBody()));
      });
    }

    protected function toArray(\SimpleXMLElement $el)
    {
      $array = [];

      if (!$el->count()) {
        return (string) $el;
      }

      $keys = [];
      foreach ($el->children() as $c) {
        $keys[] = $c->getName();
      }

      $is_assoc = count($keys) == count(array_unique($keys));

      foreach ($el->children() as $c) {
        if ($is_assoc) {
          $array[$c->getName()] = $this->toArray($c);
        }
        else {
          $array[] = $this->toArray($c);
        }
      }

      return $array;
    }
}
