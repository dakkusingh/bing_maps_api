<?php

namespace Drupal\bing_maps_api\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\bing_maps_api\BingMapsApi;
use Drupal\bing_maps_api\BingMapsApiInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'bing map' widget.
 *
 * @FieldWidget(
 *   id = "bing_map_default",
 *   label = @Translation("Bing map"),
 *   field_types = {
 *     "bing_map",
 *   }
 * )
 */
class BingMapWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Widget is operating in edit mode.
   */
  const EDIT = 1;

  /**
   * Widget is operating in search mode.
   */
  const SEARCH = 2;

  /**
   * Is the field empty.
   *
   * @var bool
   */
  protected $FieldIsEmpty;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a bing maps object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config factory.
   * @param \Drupal\bing_maps_api\BingMapsApiInterface $bing_maps_api
   *   Bing maps api.
   */
  public function __construct($plugin_id,
                              $plugin_definition,
                              FieldDefinitionInterface $field_definition,
                              array $settings,
                              array $third_party_settings,
                              ConfigFactoryInterface $config,
                              BingMapsApiInterface $bing_maps_api) {
    parent::__construct($plugin_id,
                        $plugin_definition,
                        $field_definition,
                        $settings,
                        $third_party_settings);
    $this->config = $config;
    $this->bingMapApi = $bing_maps_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container,
                                array $configuration,
                                $plugin_id,
                                $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('bing_maps_api')
    );
  }

  /**
   * Add the editing related fields to the field widget form.
   *
   * The form is pretty big, since it needs to provide lot of functionality,
   * like providing tools to do a search using the Bing services, show search
   * results, show a map, and buttons to add/edit/remove the locations. To avoid
   * cluttering the form these form elements are hidden and being shown using
   * the various buttons which are handled with #ajax callbacks. These buttons,
   * to support the various visibility setups, will define an editing_state,
   * which will be used to determine which form element needs to be shown.
   *
   * The form items in this function are providing helper functionality, so
   * the actual storage happens in other fields. To be able to tell which field
   * and delta needs updating the buttons will have these values specified in
   * their definition which will be available at submit time.
   *
   * The buttons will sometimes have a custom #name set because, when we show
   * results and buttons for a selection, the buttons will have the same name
   * and we want to be able to differentiate between the buttons.
   */
  protected function editingForm(array &$fieldset, array $form, FormStateInterface $form_state, FieldItemListInterface $items, $delta) {
    // Wrap all the fields for one $delta into a fieldset.
    $fieldset = array_merge($fieldset, [
      '#type' => 'fieldset',
      '#title' => $this->fieldDefinition->getLabel(),
      '#attributes' => [
        'id' => 'bing-map-fieldset-' . $delta,
      ],
    ]);

    $parents = array_merge($form['#parents'], [$this->fieldDefinition->getName(), $delta]);

    $triggering_element = $form_state->getTriggeringElement();
    $access = isset($triggering_element['#editing_state']);

    // Search results (hidden by default).
    $fieldset['result-area'] = [
      '#type' => 'fieldset',
      '#attached' => [],
      '#title' => $this->t('Locations'),
      '#attributes' => ['class' => ['visually-hidden', 'bing-location-results']],
      '#weight' => 998,
      '#access' => $access,
    ];
    // If the user selects location using the map then the results will be sent
    // using this form structure. This list will also be populated with search
    // results.
    $fieldset['result-area']['dynamic'] = [
      '#type' => 'fieldset',
      '#attributes' => ['class' => ['bing-location-results-dynamic', 'visually-hidden']],
      '#weight' => 998,
      '#access' => $access,
      // Prevent a fatal as we seem to lack a #attached property.
      '#attached' => [],
      'add_button' => [
        '#type' => 'submit',
        '#value' => $this->t('Add Location'),
        '#name' => implode('_', $parents) . '_dynamic_' . $delta . '_add_button',
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxFieldEditing'],
          'wrapper' => 'bing-map-fieldset-' . $delta,
          'progress' => ['type' => 'throbber', 'message' => ''],
        ],
        '#limit_validation_errors' => [$parents],
        '#validate' => [[get_class($this), 'mapSearchValidate']],
        '#submit' => [[get_class($this), 'editingSubmit']],
        '#field_name' => $this->fieldDefinition->getName(),
        '#delta' => $delta,
        '#result_type' => BingMapsApi::PIN_POINT,
        '#access' => $access,
      ],
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name:'),
        '#attributes' => ['class' => ['name']],
      ],
      'address' => [
        '#theme_wrappers' => ['form_element'],
        '#title' => $this->t('Address:'),
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => ' ',
        '#attributes' => ['class' => ['address']],
      ],
      'latlong' => [
        '#type' => 'hidden',
        '#default_value' => '',
        '#attributes' => ['id' => 'latlong'],
      ],
    ];

    // This will host the Bing Map.
    $fieldset['map'] = [
      'pinbox' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => '<img src="//ecn.dev.virtualearth.net/mapcontrol/v7.0/7.0.2014071064147.83/i/poi_search.png" alt="" />',
        '#attributes' => ['class' => ['pinbox']],
      ],
      'map' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => ' ',
        '#attributes' => [
          'id' => 'bing-map',
        ],
      ],
      '#prefix' => '<div class="map-container">',
      '#suffix' => '</div>',
      '#access' => $access,
      '#weight' => 999,
    ];
    // Displaying current field value.
    if (!$this->FieldIsEmpty) {
      $fieldset['action_remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove location'),
        '#name' => implode('_', $parents) . '_remove_button',
        '#access' => !$access,
        '#ajax' => [
          'callback' => [get_class($this), 'ajaxFieldEditing'],
          'wrapper' => 'bing-map-fieldset-' . $delta,
          'progress' => ['type' => 'throbber', 'message' => ''],
        ],
        '#limit_validation_errors' => [$parents],
        '#submit' => [
          [get_class($this), 'editingSubmit'],
          [get_class($this), 'removeSubmit'],
        ],
        '#field_name' => $this->fieldDefinition->getName(),
        '#delta' => $delta,
        '#prefix' => '<ul class="location-current location-results"><li class="last">',
      ];
    }
    // Editing helper fields. With these the user will be able to add, edit,
    // remove locations, access the Bing search services and the map.
    // Limit validation error param.
    $label = $this->FieldIsEmpty ? $this->t('Add a location') : $this->t('Edit location');
    $fieldset['action_edit'] = [
      '#type' => 'submit',
      '#value' => $label,
      '#name' => implode('_', $parents) . '_edit_button',
      '#access' => !$access,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxFieldEditing'],
        'wrapper' => 'bing-map-fieldset-' . $delta,
        'progress' => ['type' => 'throbber', 'message' => ''],
      ],
      '#limit_validation_errors' => [$parents],
      '#submit' => [[get_class($this), 'editingSubmit']],
      '#field_name' => $this->fieldDefinition->getName(),
      '#delta' => $delta,
      '#editing_state' => $this::edit,
    ];
    if (!$this->FieldIsEmpty) {
      $fieldset['info'] = [
        '#theme' => 'bing_maps_api_search_item',
        '#lat' => $items[$delta]->get('latitude')->getValue(),
        '#long' => $items[$delta]->get('longitude')->getValue(),
        '#description' => $items[$delta]->get('description')->getValue(),
        '#address' => $items[$delta]->get('address')->getValue(),
        '#suffix' => '</li></ul>',
      ];
      if ($access) {
        $fieldset['info']['#prefix'] = '<ul class="location-current location-results"><li class="last">';
      }
    }

    $fieldset['user_input'] = [
      '#prefix' => '<div class="cf bing-location-search">',
      '#type' => 'textfield',
      '#title' => $this->t('Search listings or drag the orange pin to a location on the map'),
      '#default_value' => $form_state->getValue('user_input'),
      '#access' => $access,
      '#element_validate' => [[get_class($this), 'userInputValidate']],
      '#attributes' => ['title' => $this->t('Place, business or address')],
    ];
    $fieldset['action_search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#name' => implode('_', $parents) . '_search_button',
      '#access' => $access,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxFieldEditing'],
        'wrapper' => 'bing-map-fieldset-' . $delta,
        'progress' => ['type' => 'throbber', 'message' => ''],
      ],
      '#limit_validation_errors' => [$parents],
      '#submit' => [[get_class($this), 'editingSubmit']],
      '#field_name' => $this->fieldDefinition->getName(),
      '#delta' => $delta,
      '#editing_state' => $this::search,
    ];
    $fieldset['action_cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#name' => implode('_', $parents) . '_cancel_button',
      '#access' => $access,
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxFieldEditing'],
        'wrapper' => 'bing-map-fieldset-' . $delta,
        'progress' => ['type' => 'throbber', 'message' => ''],
      ],
      '#limit_validation_errors' => [$parents],
      '#submit' => [[get_class($this), 'editingSubmit']],
      '#field_name' => $this->fieldDefinition->getName(),
      '#delta' => $delta,
      '#suffix' => '</div>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items,
                              $delta,
                              array $element,
                              array &$form,
                              FormStateInterface $form_state) {
    // Retrieve any values set in $form_state, as will be the case during AJAX
    // rebuilds of this form.
    $values = $form_state->getValue(array_merge(
      $element['#field_parents'],
      [$this->fieldDefinition->getName()]
    ));

    $props = [
      'latitude',
      'longitude',
      'description',
      'address',
      'bing_id',
      'source',
    ];

    if (!empty($values)) {
      $key_exists = FALSE;
      $values = NestedArray::getValue($values, [], $key_exists);

      if ($key_exists) {
        foreach ($props as $field_name) {
          if (isset($values[$delta][$field_name])) {
            $items[$delta]->get($field_name)->setValue($values[$delta][$field_name]);
          }
          else {
            $items[$delta]->get($field_name)->setValue('');
          }
        };
      }
    }
    $this->FieldIsEmpty = empty($items[$delta]) || !$items[$delta]->get('description')->getValue();
    $bing_config = $this->config->get('bing_maps_api.settings');
    $fieldset = [
      '#attached' => [
        'library' => ['bing_maps_api/drupal.bing_maps_api.external', 'bing_maps_api/drupal.bing_maps_api'],
        'drupalSettings' => [
          'bingMapsApi' => [
            'key' => $bing_config->get('map_key'),
            'center' => [
              'latitude' => $bing_config->get('latitude'),
              'longitude' => $bing_config->get('longitude'),
            ],
            'zoom' => $bing_config->get('zoom'),
          ],
        ],
      ],
    ];
    foreach ($props as $field_name) {
      $fieldset[$field_name] = [
        '#type' => 'value',
        '#value' => $items[$delta]->get($field_name)->getValue(),
      ] + $element;
    }
    // Put the current field value info and the elements for
    // editing into $fieldset.
    $this->editingForm($fieldset, $form, $form_state, $items, $delta);
    // Pushing the search result listing into $fieldset.
    $this->searchResults($fieldset, $form, $form_state, $delta);
    return $fieldset;
  }

  /**
   * Add the search result listing to the field widget form.
   */
  protected function searchResults(&$fieldset,
                                   array $form,
                                   FormStateInterface $form_state,
                                   $delta) {
    $triggering_element = $form_state->getTriggeringElement();
    if (isset($triggering_element['#editing_state']) && $triggering_element['#editing_state'] == $this::search) {
      // Limit validation error param.
      $parents = array_merge($form['#parents'], [$this->fieldDefinition->getName(), $delta]);

      $input = $form_state->getValue(
        [
          $this->fieldDefinition->getName(),
          $delta,
          'user_input',
        ]
      );

      $search_results[BingMapsApi::ADDRESS] = $this->bingMapApi->geocodeLookup($input);
      $search_results[BingMapsApi::POINT_OF_INTEREST] = $this->bingMapApi->businessLookup($input);
      $search_results[BingMapsApi::PHONEBOOK] = $this->bingMapApi->phonebookLookup($input);
      // Store it temporarily so we can identify what the user has picked and
      // what properties the location has.
      $fieldset['temporary_result_container'] = [
        '#type' => 'value',
        '#value' => $search_results,
      ];

      $counter = 0;
      foreach ($search_results as $type => $resultset) {
        foreach ($resultset as $item_delta => $item) {
          $name = implode('_', $parents) . '_' . $type . '_' . $item_delta;
          $fieldset['result-area'][$name] = [
            '#type' => 'fieldset',
            // Prevent a fatal as we seem to lack a #attached property.
            '#attached' => [],
            'add_button' => [
              '#type' => 'submit',
              '#value' => $this->t('Add to location'),
              '#name' => $name . '_add_button',
              '#ajax' => [
                'callback' => [get_class($this), 'ajaxFieldEditing'],
                'wrapper' => 'bing-map-fieldset-' . $delta,
                'progress' => ['type' => 'throbber', 'message' => ''],
              ],
              '#limit_validation_errors' => [$parents],
              '#submit' => [[get_class($this), 'editingSubmit']],
              '#field_name' => $this->fieldDefinition->getName(),
              '#delta' => $delta,
              '#result_type' => $type,
              '#result_delta' => $item_delta,
            ],
            'item' => [
              '#theme' => 'bing_maps_api_search_item_array',
              '#item' => $item,
            ],
          ];
          $counter++;
        }
      }
      if (empty($counter)) {
        $fieldset['result-area']['no-results'] = [
          '#type' => 'fieldset',
          '#attached' => [],
          'message' => [
            '#markup' => $this->t('Results not available at this time.'),
          ],
        ];
      }
      $fieldset['result-area']['#attributes']['class'] = [];
    }
  }

  /**
   * Ajax callback to determine which part of the form needs rebuilding.
   */
  public static function ajaxFieldEditing(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $length = !empty($triggering_element['#result_type']) ? -3 : -1;

    // Go one level up in the form, to the widgets container.
    return NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, $length));
  }

  /**
   * Validate the user input before sending it to search.
   */
  public static function userInputValidate($element, FormStateInterface &$form_state) {
    // Only validate if the user has clicked the 'Search' button. Said button is
    // the only one with '#editing_state' == 2.
    $triggering_element = $form_state->getTriggeringElement();
    $search_clicked = isset($triggering_element['#editing_state']) && $triggering_element['#editing_state'] == self::search;
    if ($search_clicked && empty($element['#value'])) {
      $form_state->setError($element, t('The input field needs to be filled.'));
    }
  }

  /**
   * Validate user input when adding location from the map.
   */
  public static function mapSearchValidate(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $field_name = $triggering_element['#field_name'];
    $delta = $triggering_element['#delta'];
    $parents = [$field_name, $delta, 'result-area', 'dynamic'];
    $data = $form_state->getValue($parents);
    $name = trim($data['name']);
    if (empty($name)) {
      $form_state->setErrorByName(implode('][', array_merge($parents, ['name'])), t('Please fill the name.'));
    }
    $latlong = array_filter(array_map('trim', explode(',', $data['latlong'])));
    if (count($latlong) != 2) {
      $form_state->setErrorByName('', t('Invalid latitude, longitude input.'));
    }
    else {
      $latitude = (float) $latlong[0];
      if ($latitude < -180.0 || $latitude > 180.0) {
        $form_state->setErrorByName('', t('Invalid latitude input.'));
      }
      $longitude = (float) $latlong[1];
      if ($longitude < -180.0 || $longitude > 180.0) {
        $form_state->setErrorByName('', t('Invalid longitude input.'));
      }
    }
  }

  /**
   * Location remove submit handler.
   */
  public static function removeSubmit($form, FormStateInterface &$form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->setValue([$triggering_element['#field_name'], $triggering_element['#delta']], []);
  }

  /**
   * Widget AJAX submit function.
   *
   * Force form rebuilding so the form changes, like #access control, can show
   * up on the UI and that the correct setting is participating in the submit /
   * validate / value process.
   *
   * This is where we store the selected location. The user can select a
   * location from the search services or by picking a place on the map.
   * In the former case the results have been already stored in the form state
   * and the pressed button has the information which was picked so all we
   * need to is to push the selected item into the right place.
   * In the latter case the location info is coming as input and need to follow
   * up on reverse geocode service to get address if there's any.
   */
  public static function editingSubmit(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->setRebuild();
    if (!empty($triggering_element['#result_type'])) {
      $field_name = $triggering_element['#field_name'];
      $delta = $triggering_element['#delta'];
      $result_type = $triggering_element['#result_type'];
      if ($result_type == BingMapsApi::PIN_POINT) {
        // User selected location using the map.
        $data = $form_state->getValue(
          [
            $field_name,
            $delta,
            'result-area',
            'dynamic',
          ]
        );

        $latlong = array_filter(array_map('trim', explode(',', $data['latlong'])));
        $addresses = BingMapsApi::reverseGeocode($latlong[0], $latlong[1]);
        $form_state->setValue([$field_name, $delta, 'latitude'], (float) $latlong[0]);
        $form_state->setValue([$field_name, $delta, 'longitude'], (float) $latlong[1]);
        $form_state->setValue([$field_name, $delta, 'description'], trim($data['name']));
        $form_state->setValue([$field_name, $delta, 'address'], !empty($addresses[0]['name']) ? $addresses[0]['name'] : '');
        $form_state->setValue([$field_name, $delta, 'bing_id'], '');
        $form_state->setValue([$field_name, $delta, 'source'], BingMapsApi::PIN_POINT);
      }
      else {
        // User selected location from the search services.
        $result_delta = $triggering_element['#result_delta'];
        $value = $form_state->getValue(
          [
            $field_name,
            $delta,
            'temporary_result_container',
            $result_type,
            $result_delta,
          ]
        );

        $props = [
          'latitude',
          'longitude',
          'description',
          'address',
          'bing_id',
          'source',
        ];

        foreach ($props as $field_item) {
          $form_state->setValue([$field_name, $delta, $field_item], $value[$field_item]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element,
                               ConstraintViolationInterface $violation,
                               array $form,
                               FormStateInterface $form_state) {
    return $element['value'];
  }

}
