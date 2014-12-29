<?php

/**
 * @file
 * Contains \Drupal\bing_maps_api\BingMapsApiInterface.
 */

namespace Drupal\bing_maps_api;


/**
 * Defines the interface for BingMapsApi.
 */
interface BingMapsApiInterface {

  /**
   * Phonebook lookup using Bing SOAP web service.
   *
   * @param string $input
   *   Term to search for.
   *
   * @return
   *   Array of search results.
   */
  public function phonebookLookup($input);

  /**
   * Business lookup using Bing SOAP web service.
   *
   * @param string $input
   *   Term to search for.
   *
   * @return
   *   Array of search results.
   */
  public function businessLookup($input);

  /**
   * Geocode lookup using Bing SOAP web service.
   *
   * @param string $input
   *   Term to search for.
   *
   * @return
   *   Array of search results.
   */
  public function geocodeLookup($input);

  /**
   * everse Geocode lookup using Bing REST service.
   *
   * @param string $latitude
   *   Latitude.
   * @param string $longitude
   *   Latitude.
   *
   * @return
   *   Array of search results.
   */
  public static function reverseGeocode($latitude, $longitude);

}
