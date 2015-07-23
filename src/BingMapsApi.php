<?php

/**
 * @file
 * Contains \Drupal\bing_maps_api\BingMapsApi.
 */

namespace Drupal\bing_maps_api;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\Client;
use Drupal\Core\Url;

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
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $Httpclient;

  /**
   * @var int
   */
  protected $limit;

  /**
   * @inheritdoc.
   */
  public function phonebookLookup($input) {
    return array();
  }

  /**
   * @inheritdoc.
   */
  public function businessLookup($input) {
    return array();
  }

  /**
   * Constructs a bing maps object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config factory.
   * @param \GuzzleHttp\Client $http_client
   *   Http client.
   */
  public function __construct(ConfigFactoryInterface $config, Client $http_client) {
    $this->config = $config;
    $this->Httpclient = $http_client;
    $this->limit = $this->config->get('bing_maps_api.settings')->get('items_per_category', 10);
  }

  /**
   * @inheritdoc.
   */
  public function geocodeLookup($input) {
    return array();
  }

  /**
   * @inheritdoc.
   */
  public static function reverseGeocode($latitude, $longitude) {
    $lookup_results = array();
    $settings = \Drupal::config('bing_maps_api.settings');
    $response = \Drupal::httpClient()->get(Url::fromUri('http://dev.virtualearth.net/REST/v1/Locations/' . $latitude . ',' . $longitude, ['query' => ['output' => 'json', 'key' => $settings->get('map_key', '')]]), array('timeout' => $settings->get('response_timeout', 10)));
    if ($response->getStatusCode() == 200 && ($results = $response->json())) {
      if (!empty($results['resourceSets'][0]['resources'])) {
        $lookup_results = $results['resourceSets'][0]['resources'];
      }
    }
    return $lookup_results;
  }
}
