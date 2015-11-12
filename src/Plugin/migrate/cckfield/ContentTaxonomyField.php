<?php

/**
 * @file
 * Contains \Drupal\content_taxonomy\Plugin\migrate\cckfield\ContentTaxonomyField.
 */

namespace Drupal\content_taxonomy\Plugin\migrate\cckfield;

use Drupal\Core\Field\Plugin\migrate\cckfield\ReferenceBase;
use Drupal\migrate\Entity\Migration;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Row;

/**
 * @MigrateCckField(
 *   id = "content_taxonomy",
 *   type_map = {
 *     "content_taxonomy" = "entity_reference"
 *   }
 * )
 */
class ContentTaxonomyField extends ReferenceBase {

  /**
   * @var string
   */
  protected $bundleMigration = 'd6_taxonomy_vocabulary';

  /**
   * {@inheritdoc}
   */
  protected function entityId() {
    return 'tid';
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    // @TODO: Find widget for content_taxonomy_tree'
    return [
      'content_taxonomy_autocomplete' => 'entity_reference_autocomplete',
      'content_taxonomy_options' => 'options_buttons',
      'content_taxonomy_select' => 'options_select',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'content_taxonomy_formatter_default' => 'entity_reference_label',
      'content_taxonomy_formatter_link' => 'entity_reference_label'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'iterator',
      'source' => $field_name,
      'process' => [
        'target_id' => [
          [
            'plugin' => 'skip_on_empty',
            'method' => 'process',
            'source' => 'value'
          ],
          [
            'plugin' => 'migration',
            'migration' => 'd6_taxonomy_term'
          ]
        ],
      ]
    ];
    $migration->mergeProcessOfProperty($field_name, $process);

    $migration_dependencies = $migration->get('migration_dependencies');
    $migration_dependencies['required'][] = 'd6_taxonomy_term';
    $migration->set('migration_dependencies', $migration_dependencies);
  }

  /**
   * {@inheritdoc}
   */
  public function processField(MigrationInterface $migration) {
    parent::processField($migration);
    $this->addMigrationDependencies($migration);
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldInstance(MigrationInterface $migration) {
    parent::processFieldInstance($migration);
    $this->addMigrationDependencies($migration);
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldStorageSettings(Row $row) {
    $settings['target_type'] = 'taxonomy_term';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldInstanceSettings(Row $row) {
    $source_settings = $row->getSourceProperty('global_settings');
    $settings['handler'] = 'default:taxonomy_term';
    $settings['handler_settings']['target_bundles'] = $this->migrateTaxonomyVocabularies([$source_settings['vid']]);
    return $settings;
  }

  /**
   * Look up migrated vocabulary IDs from the d6_taxonomy_vocabulary migration.
   *
   * @param $source_ids
   *   The source role IDs.
   *
   * @return array
   *   The migrated role IDs.
   */
  protected function migrateTaxonomyVocabularies($source_ids) {
    // Configure the migration process plugin to look up migrated IDs from
    // the d6_user_role migration.
    $migration_plugin_configuration = [
      'migration' => $this->bundleMigration,
    ];

    $migration = Migration::create();
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $row = new Row([], []);
    $migrationPlugin = $this->migratePluginManager
      ->createInstance('migration', $migration_plugin_configuration, $migration);

    $ids = [];
    foreach ($source_ids as $role) {
      $ids[] = $migrationPlugin->transform($role, $executable, $row, NULL);
    }
    return array_combine($ids, $ids);
  }

  /**
   * @param \Drupal\migrate\Entity\MigrationInterface $migration
   *
   * @return \Drupal\migrate\Entity\MigrationInterface
   */
  protected function addMigrationDependencies(MigrationInterface $migration) {
    $migration_dependencies = $migration->getMigrationDependencies();
    if (!in_array($this->bundleMigration, $migration_dependencies['required'])) {
      $migration_dependencies['required'][] = $this->bundleMigration;
      $migration->set('migration_dependencies', $migration_dependencies);
    }
  }

}
