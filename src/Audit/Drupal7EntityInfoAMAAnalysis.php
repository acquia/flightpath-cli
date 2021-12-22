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
class Drupal7EntityInfoAMAAnalysis extends AcquiaMigrateAnalysis
{
  /**
   *
   */
    public function gather(Sandbox $sandbox)
    {
        // Gather module data from drush pm-list.
        parent::gather($sandbox);
        $this->getFieldTypeInformation();
        $this->getEntityInfo();
        $this->getSchemaAnalysis();

        $map = $this->get('table_module_map');
        $entity_info = $this->get('entity_info');
        $modules = $this->get('modules');

        foreach ($entity_info as $type => &$info) {
          if (!isset($info['base table'])) {
            continue;
          }
          if (!isset($info['module'])) {
            $info['module'] = $map[$info['base table']];
          }
          $info['vetted'] = $modules[$info['module']]['strategy'];
        }

        $this->set('entity_info', $entity_info);

        $field_info = $this->get('field_info');
        $bundle_fields = [];
        foreach ($field_info as $field_name => $info) {
          foreach ($info['bundles'] as $entity_type => $bundles) {
            foreach ($bundles as $bundle) {
              $bundle_fields[$entity_type][$bundle][$field_name] = $modules[$info['module']]['strategy'];
            }
          }
        }

        $this->set('entity_bundle_field_migration_status', $bundle_fields);
    }
}
