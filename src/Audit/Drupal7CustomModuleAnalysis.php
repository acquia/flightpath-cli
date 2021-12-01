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
class Drupal7CustomModuleAnalysis extends Drupal7ModuleUpdateAnalysis
{

  /**
   *
   */
    public function gather(Sandbox $sandbox)
    {
        // Gather module data from drush pm-list.
        parent::gather($sandbox);

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
          list($lines, $bytes, $filepath) = explode(' ', $line, 3);
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
              $modules[$module]['stat']['js']++;
              $modules[$module]['stat']['size'] += $bytes;
              break;

            case 'css':
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
}
