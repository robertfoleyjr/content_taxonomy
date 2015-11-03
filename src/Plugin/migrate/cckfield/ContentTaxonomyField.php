<?php

/**
 * @file
 * Contains \Drupal\content_taxonomy\Plugin\migrate\cckfield\ContentTaxonomyField.
 */

namespace Drupal\content_taxonomy\Plugin\migrate\cckfield;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Entity\Migration;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @MigrateCckField(
 *   id = "content_taxonomy",
 *   type_map = {
 *     "content_taxonomy" = "entity_reference"
 *   }
 * )
 */
class ContentTaxonomyField extends CckFieldPluginBase {

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

    $migration_dependencies = $migration->get('migration_dependencies');
    $migration_dependencies['required'][] = 'd6_taxonomy_vocabulary';
    $migration->set('migration_dependencies', $migration_dependencies);
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldStorageSettings($value) {
    $settings = [];
    $settings['target_type'] = 'taxonomy_term';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldSettings($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    list($widget_type, $widget_settings, $field_settings, $source_field_type) = $value;
    $settings = [];

    try {
      $vocabulary = $this->getTaxonomyVocabularyMigration()->transform($field_settings['vid'], $migrate_executable, $row, $destination_property);
      $settings['handler_settings']['target_bundles'][$vocabulary] = $vocabulary;
    }
    catch (MigrateSkipRowException $e) {

    }

    return $settings;
  }

  /**
   * Initialize the d6_taxonomy_vocabulary migration.
   *
   * @return \Drupal\migrate\Plugin\MigrateProcessInterface
   */
  protected function getTaxonomyVocabularyMigration() {
    if (!isset($this->migrationPlugin)) {

      // Configure the migration process plugin to look up migrated IDs from
      // the d6_file migration.
      $migration_plugin_configuration = [
        'source' => ['vid'],
        'migration' => 'd6_taxonomy_vocabulary',
      ];

      $migration = Migration::create();

      $this->migrationPlugin = \Drupal::service('plugin.manager.migrate.process')
        ->createInstance('migration', $migration_plugin_configuration, $migration);
    }

    return $this->migrationPlugin;
  }

}
