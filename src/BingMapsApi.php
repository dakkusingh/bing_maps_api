<?php

namespace Drupal\bing_maps_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;

/**
 * Returns responses for Media entity routes.
 */
abstract class BingMapsApi implements BingMapsApiInterface {

  /**
   * Lat/long specified by address.
   */
  const ADDRESS = 1;

  /**
   * Lat/long specified by Point of Interest.
   */
  const POINT_OF_INTEREST = 2;

  /**
   * Lat/long specified by placing pin on map.
   */
  const PIN_POINT = 3;

  /**
   * Lat/long specified by phonebook.
   */
  const PHONEBOOK = 4;

  /**
   * ConfigFactoryInterface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Limit.
   *
   * @var int
   */
  protected $limit;

  /**
   * Constructs a bing maps object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config;
    $this->limit = $this->config->get('bing_maps_api.settings')->get('items_per_category', 10);
  }

  /**
   * {@inheritdoc}
   */
  public function phonebookLookup($input) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function businessLookup($input) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function geocodeLookup($input) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function reverseGeocode($latitude, $longitude) {
    $lookup_results = [];
    $settings = \Drupal::config('bing_maps_api.settings');
    $url = Url::fromUri('http://dev.virtualearth.net/REST/v1/Locations/' . $latitude . ',' . $longitude, ['query' => ['key' => $settings->get('map_key', '')]])->toString();
    $response = \Drupal::httpClient()->get($url, ['timeout' => $settings->get('response_timeout', 10)]);
    if ($response->getStatusCode() == 200 && ($results = Json::decode($response->getBody(TRUE)))) {
      if (!empty($results['resourceSets'][0]['resources'])) {
        $lookup_results = $results['resourceSets'][0]['resources'];
      }
    }
    return $lookup_results;
  }

}
