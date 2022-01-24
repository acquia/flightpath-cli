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
class AcquiaMigrateAnalysis extends ModuleAnalysis
{

  const PREFLIGHT_JSON = 'https://raw.githubusercontent.com/acquia/acquia-migrate-accelerate/metadata/preflight.json';

  /**
   * Gather migarate details.
   */
    public function gather(Sandbox $sandbox)
    {
        // Gather module data from drush pm-list.
        parent::gather($sandbox);

        $modules = $this->get('modules');
        $modules = $this->getModuleFilepathData($modules);
        $modules = $this->getDecoratedModuleData($modules);

        foreach ($modules as &$module) {
          $module['strategy'] = 'unknown';
        }

        $installed = array_filter($modules, function ($module) {
          return $module['status'] != 'Not installed';
        });

        $recommendations = $this->runCacheable(self::PREFLIGHT_JSON, function ($item) {
          $item->expiresAfter(86400);
          return json_decode(file_get_contents(self::PREFLIGHT_JSON), true);
        });

        $vetted = $unvetted = $unknown = [];
        foreach ($recommendations as $project => $rule) {
          if (!isset($modules[$project])) {
            continue;
          }
          if ($rule['vetted']) {
            $vetted[] = $project;
            $modules[$project]['strategy'] = 'ama_vetted';
            $modules[$project]['note'] = $rule['note'];
          }
          else {
            $unvetted[] = $project;
            $modules[$project]['strategy'] = 'ama_unvetted';
            $modules[$project]['note'] = $rule['note'];
          }
        }
        $unknown = array_diff(array_keys($installed), $vetted, $unvetted);
        $this->set('vetted', $vetted);
        $this->set('unvetted', array_diff($unvetted, $vetted));
        $this->set('unknown', $unknown);
        $this->set('installed', array_keys($installed));
        $this->set('modules', $modules);

        $this->set('iUvetted', array_intersect($vetted, $unknown));
        $this->set('iUnvetted', array_intersect($vetted, $unvetted));
        $this->set('iUunvetted', array_intersect($unvetted, $unknown));
    }

    /**
     * Decorate module data with filepath metadata.
     */
    protected function getModuleFilepathData(array $modules):array
    {
      // Get the locations of all the modules in the codebase.
      $filepaths = $this->target->getService('exec')->run('find $DRUSH_ROOT -name \*.info -type f -print -exec grep -in project {} /dev/null \;', function ($output) {
        return array_map(function ($line) {
          return trim($line);
        }, explode(PHP_EOL, $output));
      });

      $module_filepaths = [];
      $project_map = [];

      foreach ($filepaths as $filepath) {
        $project_data = NULL;
        // Parse for project data.
        $parsed = explode(':', $filepath);
        if (count($parsed) == 3) {
          list($filepath, $line, $project_data) = $parsed;
        }
        list($module_name, , ) = explode('.', basename($filepath));
        $module_filepaths[$module_name] = $filepath;

        if (isset($project_data)) {
          preg_match('/project ?= ?"?([^"]+)"?/', $project_data, $matches);
          if (!empty($matches)) {
            $project_map[$module_name] = $matches[1];
          }
        }
      }

      foreach($modules as $module => &$info) {
        $info['filepath'] = $module_filepaths[$module];
        $info['dirname'] = str_replace($this->target['drush.root'] . '/', '', dirname($info['filepath']));
        $info['name'] = $module;

        if (isset($project_map[$module])) {
          $info['project'] = $project_map[$module];
        }

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

          $project = $info['project'] ?? $module;
          $has_d7_versions = $this->getVersions($project, '7.x');
          $has_current_versions = $this->getVersions($project, 'current');

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

    protected function getCustomModuleAnalysis()
    {
        $unknown = $this->get('unknown');
        $modules = $this->get('modules');

        $output = $this->target->getService('exec')->run('find $DRUSH_ROOT/*/*/modules -type f -exec wc -c -l {} \;', function ($output) {
          return array_map(function ($line) {
            return trim($line);
          }, explode(PHP_EOL, $output));
        });

        $basestat = [
          'size' => 0,
          'files' => 0,
          'php' => 0,
          'css' => 0,
          'js' => 0,
          'tpl' => 0,
          'media' => 0,
          'media_size' => 0,
          'other' => 0
        ];

        foreach ($output as $line) {
          $parsed = explode(' ', $line, 3);
          if (count($parsed) != 3) {
            continue;
          }
          list($lines, $bytes, $filepath) = $parsed;
          $ext = pathinfo($filepath, PATHINFO_EXTENSION);

          // Maybe multiple matches if file belongs to a sub-module.
          $candidates = array_filter($modules, function ($m) use ($filepath) {
            return strpos($filepath, $m['dirname']) !== false;
          });

          // Cannot associate file with module so exclude.
          if (empty($candidates)) {
            continue;
          }

          // Use candidate with longest dirname (highest specificity).
          uasort($candidates, function ($a, $b) {
            if (strlen($a['dirname']) == strlen($b['dirname'])) {
              return 0;
            }
            return strlen($a['dirname']) > strlen($b['dirname']) ? -1 : 1;
          });

          $module = key($candidates);

          if (!isset($modules[$module]['stat'])) {
            $modules[$module]['stat'] = $basestat;
          }

          $modules[$module]['stat']['files']++;

          switch (strtolower($ext)) {
            case 'php':
            case 'inc':
            case 'install':
            case 'module':
              if (strpos($filepath, 'tpl.php') === FALSE) {
                $modules[$module]['stat']['php']++;
              }
              else {
                $modules[$module]['stat']['tpl']++;
              }
              $modules[$module]['stat']['size'] += $bytes;
              break;

            case 'js':
            case 'coffee':
              $modules[$module]['stat']['js']++;
              $modules[$module]['stat']['size'] += $bytes;
              break;

            case 'css':
            case 'sass':
              $modules[$module]['stat']['css']++;
              $modules[$module]['stat']['size'] += $bytes;
              break;

            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'bmp':
            case 'mp3':
            case 'mp4':
            case 'mpeg':
            case 'avi':
            case 'pdf':
            case 'doc':
            case 'docx':
            case 'xls':
            case 'xlsx':
              $modules[$module]['stat']['media']++;
              $modules[$module]['stat']['media_size'] += $bytes;
              break;

            default:
              $modules[$module]['stat']['other']++;
              $modules[$module]['stat']['size'] += $bytes;
              break;
          }
        }

        $this->set('modules', $modules);
    }

    /**
     * Load Drupal 7 entity information.
     */
    protected function getEntityInfo()
    {
        $data = $this->target->getService('drush')->runtime(function () {
          return [entity_get_info()];
        });

        $this->set('entity_info', $data[0]);
    }

    /**
     * Get field type information.
     */
    protected function getFieldTypeInformation()
    {
        $data = $this->target->getService('drush')->runtime(function () {
          return [field_info_field_types(), field_info_fields()];
        });

        $this->set('field_types', $data[0]);
        $this->set('field_info', $data[1]);
    }

    /**
     * Load schema analysis data.
     */
    protected function getSchemaAnalysis()
    {
        $data = $this->target->getService('drush')->runtime(function () {
          require_once 'includes/install.inc';
          drupal_load_updates();
          $i = module_implements('schema');
          global $databases;

          $r = db_query('SELECT table_name, table_rows FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db AND table_name != :heartbeat', [
            ':db' => $databases['default']['default']['database'],
            ':heartbeat' => '__ACQUIA_MONITORING'
          ])->fetchAllKeyed();

          $f = function ($m) { return module_invoke($m, 'schema'); };
          return [array_combine($i, array_map($f, $i)), $r];
        });

        $this->set('schema_by_module', $data[0]);
        $schema = [];
        foreach ($data[0] as $module => $tables) {
          if (empty($tables)) {
            continue;
          }
          foreach ($tables as $table => $info) {
            $schema[$table] = $module;
          }
        }
        $this->set('table_module_map', $schema);
        $this->set('table_row_stats', $data[1]);
    }
}
