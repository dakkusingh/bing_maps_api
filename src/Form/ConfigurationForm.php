<?php

namespace Drupal\bing_maps_api\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * Configure bing maps settings for this site.
 */
class ConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bing_maps_api_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bing_maps_api.settings');

    $form['bing_map_map_defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Default map settings'),
      '#open' => TRUE,
    ];
    $form['bing_map_map_defaults']['latitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default latitude'),
      '#default_value' => $config->get('latitude'),
      '#required' => TRUE,
    ];
    $form['bing_map_map_defaults']['longitude'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default longitude'),
      '#default_value' => $config->get('longitude'),
      '#required' => TRUE,
    ];
    $form['bing_map_map_defaults']['zoom'] = [
      '#type' => 'select',
      '#options' => array_combine(range(1, 12), range(1, 12)),
      '#title' => $this->t('Default zoom'),
      '#default_value' => $config->get('zoom'),
      '#required' => TRUE,
    ];
    $form['bing_map_api'] = [
      '#type' => 'details',
      '#title' => $this->t('Api Settings'),
      '#open' => TRUE,
    ];
    $form['bing_map_api']['map_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map key'),
      '#default_value' => $config->get('map_key'),
      '#required' => TRUE,
    ];
    $form['bing_map_api']['map_appid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map appid'),
      '#default_value' => $config->get('map_appid'),
      '#required' => TRUE,
    ];
    $form['bing_map_api']['response_timeout'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Response time out'),
      '#default_value' => $config->get('response_timeout'),
      '#element_validate' => [['\Drupal\Core\Render\Element\Number', 'validateNumber']],
      '#required' => TRUE,
    ];
    $form['bing_map_api']['connection_timeout'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Connection time out'),
      '#default_value' => $config->get('connection_timeout'),
      '#element_validate' => [['\Drupal\Core\Render\Element\Number', 'validateNumber']],
      '#required' => TRUE,
    ];
    $form['bing_map_api']['items_per_category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Items per category'),
      '#default_value' => $config->get('items_per_category'),
      '#element_validate' => [['\Drupal\Core\Render\Element\Number', 'validateNumber']],
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach (['latitude', 'longitude'] as $field) {
      if (($value = $form_state->getValue($field)) && !is_numeric($value)) {
        $form_state->setErrorByName($field, $this->t('Please enter a numeric value.'));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('bing_maps_api.settings');
    $field_names = [
      'response_timeout',
      'connection_timeout',
      'items_per_category',
      'latitude',
      'longitude',
      'zoom',
      'map_key',
      'map_appid',
    ];
    foreach ($field_names as $field_name) {
      $config->set($field_name, $form_state->getValue($field_name));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'response_timeout',
      'connection_timeout',
      'items_per_category',
      'latitude',
      'longitude',
      'zoom',
      'map_key',
      'map_appid',
    ];
  }

}
