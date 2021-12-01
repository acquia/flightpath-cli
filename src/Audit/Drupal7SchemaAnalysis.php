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
class Drupal7SchemaAnalysis extends Drupal7ModuleUpdateAnalysis
{
  /**
   *
   */
    public function gather(Sandbox $sandbox)
    {
        // Gather module data from drush pm-list.
        parent::gather($sandbox);

        $data = $this->target->getService('drush')->runtime(function () {
          require 'includes/install.inc';
          drupal_load_updates();
          $i = module_implements('schema');
          global $databases;

          $r = db_query('SELECT table_name, table_rows FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db', [
            ':db' => $databases['default']['default']['database']
          ])->fetchAllKeyed();
          return [array_combine($i, array_map(fn($m) => module_invoke($m, 'schema'), $i)), $r];

        });

        $this->set('schema_by_module', $data[0]);
        $this->set('table_row_stats', $data[1]);
    }
}
