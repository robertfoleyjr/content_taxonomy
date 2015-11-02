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
use Drupal\migrate\Plugin\MigrateProcessInterface;
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
class ContentTaxonomyField extends CckFieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migration process plugin, configured for lookups in d6_file.
   *
   * @var \Drupal\migrate\Plugin\MigrateProcessInterface
   */
  protected $migrationPlugin;

  /**
   * Constructs a CckFile plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\migrate\Entity\MigrationInterface $migration
   *   The current migration.
   * @param \Drupal\migrate\Plugin\MigrateProcessInterface $migration_plugin
   *   An instance of the 'migration' process plugin.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, MigrateProcessInterface $migration_plugin) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
    $this->migrationPlugin = $migration_plugin;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    $debug = 1;

    // Configure the migration process plugin to look up migrated IDs from
    // the d6_file migration.
    $migration_plugin_configuration = [
      'source' => ['vid'],
      'migration' => 'd6_taxonomy_vocabulary',
    ];

    if (!isset($migration)) {
      $migration = Migration::create();
    }

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('plugin.manager.migrate.process')->createInstance('migration', $migration_plugin_configuration, $migration)
    );
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
    // @todo adjust field formatter settings
    $debug = 1;
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
      $vocabulary = $this->migrationPlugin->transform($field_settings['vid'], $migrate_executable, $row, $destination_property);
      $settings['handler_settings']['target_bundles'][$vocabulary] = $vocabulary;
    }
    catch (MigrateSkipRowException $e) {

    }

    return $settings;
  }

}
