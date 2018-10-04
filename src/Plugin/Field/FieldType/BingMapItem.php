<?php

namespace Drupal\bing_maps_api\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'Bing Map' field type.
 *
 * @FieldType(
 *   id = "bing_map",
 *   label = @Translation("Bing map field"),
 *   description = @Translation("This field stores bing map data in the database."),
 *   default_widget = "bing_map_default",
 *   default_formatter = "bing_map_default"
 * )
 */
class BingMapItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field) {
    $columns = [
      // Required field which stores the latitude of a location.
      'latitude' => [
        'type' => 'float',
        'not null' => TRUE,
        'default' => 0,
        'description' => 'Latitude.',
      ],
      // Required field which stores the longitude of a location.
      'longitude' => [
        'type' => 'float',
        'not null' => TRUE,
        'default' => 0,
        'description' => 'Longitude.',
      ],
      // Required field which stores the description of a location, like an
      // address, business name, point of interest.
      'description' => [
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
        'default' => '',
        'description' => 'Description of the location.',
      ],
      // Optional field which stores the address of a location.
      'address' => [
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
        'default' => '',
        'description' => 'Address of the location.',
      ],
      // Optional field which stores the bing id of a location. Only gets filled
      // if the user picks a business and bing provides an id.
      'bing_id' => [
        'type' => 'varchar',
        'length' => '255',
        'not null' => TRUE,
        'default' => '',
        'description' => 'Optional Bing location ID.',
      ],
      // Required field which stores the selected location's source. It can come
      // from either business lookup, phonebook lookup, address lookup or the
      // user might have picked a location from the map.
      'source' => [
        'type' => 'int',
        'size' => 'tiny',
        'default' => 0,
        'description' => 'Bing result source.',
      ],
    ];
    return [
      'columns' => $columns,
      'indexes' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $description = $this->get('description')->getValue();
    return empty($description);
  }

  /**
   * {@inheritdoc}
   */
  public static $propertyDefinitions;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['latitude'] = DataDefinition::create('float')
      ->setLabel(t('Latitude'));

    $properties['longitude'] = DataDefinition::create('float')
      ->setLabel(t('Longitude'));

    $properties['description'] = DataDefinition::create('string')
      ->setLabel(t('Description'));

    $properties['address'] = DataDefinition::create('string')
      ->setLabel(t('Address'));

    $properties['bing_id'] = DataDefinition::create('string')
      ->setLabel(t('Bing id'));

    $properties['source'] = DataDefinition::create('integer')
      ->setLabel(t('Source'));

    return $properties;
  }

}
