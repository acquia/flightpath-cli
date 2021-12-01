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
class Drupal7FieldTypesAMAAnalysis extends Drupal7ModuleUpdateAnalysis
{
  /**
   *
   */
    public function gather(Sandbox $sandbox)
    {
        // Gather module data from drush pm-list.
        parent::gather($sandbox);

        $data = $this->target->getService('drush')->runtime(function () {
          return [field_info_field_types(), field_info_fields()];
        });

        $this->set('field_types', $data[0]);
        $this->set('field_info', $data[1]);
    }
}
