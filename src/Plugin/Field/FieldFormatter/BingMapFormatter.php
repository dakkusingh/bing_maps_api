<?php

/**
 * @file
 * Contains \Drupal\bing_maps_api\Plugin\Field\FieldFormatter\BingMapFormatter.
 */

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
  public function viewElements(FieldItemListInterface $items) {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[] = array('#plain_text' => $item->get('description')->getValue());
    }

    return $elements;
  }

}
