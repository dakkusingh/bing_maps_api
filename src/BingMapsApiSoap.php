<?php

namespace Drupal\bing_maps_api;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Html;

/**
 * Returns responses for Media entity routes.
 */
class BingMapsApiSoap extends BingMapsApi {

  /**
   * Soap Client.
   *
   * @var arrayof\nusoap_client
   */
  protected $soapClients = [];

  /**
   * Set up a SOAP client to a Bing service.
   *
   * @param string $url
   *   Url to process.
   *
   * @return string
   *   Processed Url.
   */
  protected function createClient($url) {
    if (!isset($this->soapClients[$url])) {
      $settings = $this->config->get('bing_maps_api.settings');
      $connection_timeout = $settings->get('connection_timeout', 5);
      $response_timeout = $settings->get('response_timeout', 10);
      $client = new \nusoap_client($url, TRUE, FALSE, FALSE, FALSE, FALSE, $connection_timeout, $response_timeout);
      // Nusoap library's default encoding is ISO-8859-1.
      // 1) We work with UTF-8.
      // 2) Certain Bing services only accepts UTF-8.
      $client->soap_defencoding = 'UTF-8';
      $this->soapClients[$url] = $client;
    }

    return $this->soapClients[$url];
  }

  /**
   * {@inheritdoc}
   */
  public function phonebookLookup($input) {
    $lookup_results = [];
    $client = $this->createClient('http://api.bing.net/search.wsdl?Version=2.2');

    $request_params = [
    // Nusoap will escape it.
      'Query' => $input,
      'AppId' => $this->config->get('bing_maps_api.settings')->get('map_appid', ''),
      'Sources' => ['SourceType' => ['PhoneBook']],
      'Phonebook' => ['Count' => $this->limit],
    ];
    $request_processed = new \soapval('parameters', 'SearchRequest', $request_params, TRUE, 'http://schemas.microsoft.com/LiveSearch/2008/03/Search');
    // Next 3 lines are taken using the example in call() in the nusoap library.
    if (is_null($client->wsdl)) {
      $client->loadWSDL();
    }
    // Nusoap will create the XML as <parameters>...</parameters> but Bing seems
    // to expect the <SearchRequest><parameters>...</parameters></SearchRequest>
    // format, so, we'll force this structure in the payload.
    // serializeRPCParameters() takes care of the parameters, then we slap on
    // the necessary tag. The reason this works is, that call() is not going to
    // transform string variables.
    $soap_payload = $client->wsdl->serializeRPCParameters('Search', 'input', ['parameters' => $request_processed], $client->bindingType);
    $soap_payload = '<SearchRequest>' . $soap_payload . '</SearchRequest>';
    $webservice_result = $client->call('Search', $soap_payload);
    if (!empty($webservice_result['parameters']['Phonebook']['Results']['PhonebookResult']) && ($results = $this->ensureResultStructure($webservice_result['parameters']['Phonebook']['Results']['PhonebookResult']))) {
      foreach ($results as $item) {
        if (!empty($item['Title']) && Unicode::validateUtf8($item['Title']) && !empty($item['Latitude']) && !empty($item['Longitude'])) {
          $lookup_results[] = [
            'latitude' => (float) $item['Latitude'],
            'longitude' => (float) $item['Longitude'],
            'description' => $item['Title'],
            'address' => empty($item['Address']) || !Unicode::validateUtf8($item['Address']) ? '' : $item['Address'],
            'bing_id' => '',
            'source' => BingMapsApi::PHONEBOOK,
          ];
        }
      }
    }
    return $lookup_results;
  }

  /**
   * {@inheritdoc}
   */
  public function businessLookup($input) {
    $lookup_results = [];
    $client = $this->createClient('http://dev.virtualearth.net/webservices/v1/metadata/searchservice/dev.virtualearth.net.webservices.v1.search.wsdl');

    // Nusoap is not using namespace prefixes for elements which is freaking out
    // Bing's service, so constructing the request ourselves (instead of hacking
    // nusoap to do its job).
    $namespaces = [
      'xmlns="http://dev.virtualearth.net/webservices/v1/search/contracts"',
      'xmlns:q1="http://dev.virtualearth.net/webservices/v1/common"',
      'xmlns:q2="http://dev.virtualearth.net/webservices/v1/search"',
    ];
    $payload = '
    <Search ' . implode(' ', $namespaces) . '>
      <request>
        <q1:Credentials>
          <q1:ApplicationId>' . $this->config->get('bing_maps_api.settings')->get('map_key', '') . '</q1:ApplicationId>
        </q1:Credentials>
        <q2:Query>' . Html::escape($input) . '</q2:Query>
        <q2:SearchOptions><q2:Count>' . $this->limit . '</q2:Count></q2:SearchOptions>
      </request>
    </Search>';
    $webservice_result = $client->call('Search', $payload);
    if (!empty($webservice_result['SearchResult']['ResultSets']['SearchResultSet']['Results']['SearchResultBase']) && ($results = $this->ensureResultStructure($webservice_result['SearchResult']['ResultSets']['SearchResultSet']['Results']['SearchResultBase']))) {
      foreach ($results as $item) {
        if (!empty($item['Name']) && Unicode::validateUtf8($item['Name']) && !empty($item['LocationData']['Locations']['GeocodeLocation']) && ($location_data = $this->ensureResultStructure($item['LocationData']['Locations']['GeocodeLocation']))) {
          $lookup_results[] = [
            'latitude' => (float) $location_data[0]['Latitude'],
            'longitude' => (float) $location_data[0]['Longitude'],
            'description' => $item['Name'],
            'address' => empty($item['Address']['FormattedAddress']) || !Unicode::validateUtf8($item['Address']['FormattedAddress']) ? '' : $item['Address']['FormattedAddress'],
            'bing_id' => empty($item['Id']) ? '' : $item['Id'],
            'source' => BingMapsApi::POINT_OF_INTEREST,
          ];
        }
      }
    }
    return $lookup_results;
  }

  /**
   * {@inheritdoc}
   */
  public function geocodeLookup($input) {
    $lookup_results = [];
    $client = $this->createClient('http://dev.virtualearth.net/webservices/v1/metadata/geocodeservice/geocodeservice.wsdl');

    // Nusoap is not using namespace prefixes for elements which is freaking out
    // Bing's service, so constructing the request ourselves (instead of hacking
    // nusoap to do its job).
    $namespaces = [
      'xmlns="http://dev.virtualearth.net/webservices/v1/geocode/contracts"',
      'xmlns:q1="http://dev.virtualearth.net/webservices/v1/common"',
      'xmlns:q2="http://dev.virtualearth.net/webservices/v1/geocode"',
    ];
    $payload = '
    <Geocode ' . implode(' ', $namespaces) . ' xsi:type="i0:Geocode">
      <request>
        <q1:Credentials>
          <q1:ApplicationId>' . $this->config->get('bing_maps_api.settings')->get('map_key', '') . '</q1:ApplicationId>
        </q1:Credentials>
        <q2:Options><q2:Count>' . $this->limit . '</q2:Count><q2:Filters/></q2:Options>
        <q2:Query>' . Html::escape($input) . '</q2:Query>
      </request>
    </Geocode>';
    $webservice_result = $client->call('Geocode', $payload);
    if (!empty($webservice_result['GeocodeResult']['Results']['GeocodeResult']) && ($results = $this->ensureResultStructure($webservice_result['GeocodeResult']['Results']['GeocodeResult']))) {
      foreach ($results as $item) {
        if (!empty($item['Locations']['GeocodeLocation']) && ($location_data = $this->ensureResultStructure($item['Locations']['GeocodeLocation']))) {
          $lookup_results[] = [
            'latitude' => (float) $location_data[0]['Latitude'],
            'longitude' => (float) $location_data[0]['Longitude'],
            'description' => $input,
            'address' => empty($item['Address']['FormattedAddress']) || !Unicode::validateUtf8($item['Address']['FormattedAddress']) ? '' : $item['Address']['FormattedAddress'],
            'bing_id' => '',
            'source' => BingMapsApi::ADDRESS,
          ];
        }
      }
    }
    return $lookup_results;
  }

  /**
   * Ensure result array being an array of results.
   *
   * @param array|string $soap_result_structure
   *   Structure to process.
   *
   * @return array
   *   Processed results.
   */
  protected function ensureResultStructure($soap_result_structure) {
    $results = [];
    if (is_array($soap_result_structure)) {
      // Bing sometimes provides an array of results and sometime just an array
      // which IS the result... Cute isn't it ? So if the array key is numeric
      // then let's assume we have an array of results. Otherwise assume that
      // the variable is one result, so push it into an array.
      $results = is_numeric(key($soap_result_structure)) ? $soap_result_structure : [$soap_result_structure];
    }
    return $results;
  }

}
