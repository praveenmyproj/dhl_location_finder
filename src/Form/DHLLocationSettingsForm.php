<?php

namespace Drupal\dhl_location_finder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * DHL Location Finder Settings Form.
 */
class DHLLocationSettingsForm extends ConfigFormBase {

  /**
   * Settings Config Variable.
   *
   * @var DrupalSettings
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->settings = 'dhl_location.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dhl_location_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      $this->settings,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config($this->settings);
    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API URL'),
      '#description' => $this->t('Add the API URL to get the response'),
      '#default_value' => $config->get('api_url'),
    ];
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API KEY'),
      '#description' => $this->t('Add the API key for DHL authorization.'),
      '#default_value' => $config->get('api_key'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->settings)
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();
    return parent::submitForm($form, $form_state);
  }

}
