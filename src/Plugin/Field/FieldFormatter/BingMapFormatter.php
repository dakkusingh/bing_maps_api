<?php

namespace Drupal\bing_maps_api\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'bing map default' formatter.
 *
 * @FieldFormatter(
 *   id = "bing_map_default",
 *   label = @Translation("Location"),
 *   field_types = {
 *     "bing_map",
 *   },
 *   quickedit = {
 *     "editor" = "plain_text"
 *   }
 * )
 */
class BingMapFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $item) {
      $elements[] = ['#plain_text' => $item->get('description')->getValue()];
    }

    return $elements;
  }

}
