<?php

namespace Drupal\dhl_location_finder\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * DHL Location Finder Form.
 */
class DHLLocationForm extends FormBase {

  /**
   * Country Manager Variable.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  private $countryManager;

  /**
   * Constructs a Location Finder.
   *
   * @param \Drupal\Core\Locale\CountryManagerInterface $country_manager
   *   The country manager.
   */
  public function __construct(CountryManagerInterface $country_manager) {
    $this->countryManager = $country_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('country_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dhl_location_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $this->countryManager->getList(),
      '#required' => TRUE,
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
    ];
    $form['pincode'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'set_location_results_wrapper',
      ],
    ];
    $form['location_results'] = [
      '#prefix' => '<div id="set_location_results_wrapper">',
      '#suffix' => '</div>',
      '#markup' => '',
      '#weight' => 100,
    ];
    $apiUrl = $this->config('dhl_location.settings')->get('api_url');
    $apiKey = $this->config('dhl_location.settings')->get('api_key');
    if ($form_state->getTriggeringElement()) {
      $params = [
        'radius' => 2500,
        'limit' => 20,
        'hideClosedLocations' => 'true',
        'currentDate' => date('d-m-Y'),
      ];
      if (!empty($form_state->getValue('country'))) {
        $params['countryCode'] = $form_state->getValue('country');
      }
      if (!empty($form_state->getValue('city'))) {
        $params['addressLocality'] = $form_state->getValue('city');
      }
      if (!empty($form_state->getValue('pincode'))) {
        $params['postalCode'] = $form_state->getValue('pincode');
      }
      $finalResult = $opening = [];
      $results = \Drupal::httpClient()->get($apiUrl . '?' . http_build_query($params, '', '&'), [
        'verify' => TRUE,
        'headers' => [
          'Content-type' => 'application/json',
          'DHL-API-Key' => $apiKey,
        ],
      ])->getBody()->getContents();
      $result = json_decode($results, TRUE);

      if (!empty($result)) {
        foreach ($result['locations'] as $location) {
          // Remove odd numbers.
          if ((int) filter_var($location['place']['address']['streetAddress'], FILTER_SANITIZE_NUMBER_INT) % 2 !== 0) {
            continue;
          }
          // Remove that are not working on weekends.
          $week = array_column($location['openingHours'], 'dayOfWeek');
          if (!in_array("http://schema.org/Saturday", $week) && !in_array("http://schema.org/Sunday", $week)) {
            continue;
          }
          $locResult['locationName'] = $location['name'];
          $locResult['address'] = $location['place']['address'];
          foreach ($location['openingHours'] as $hours) {
            $opening[strtolower(explode('.org/', $hours['dayOfWeek'])[1])] = $hours['opens'] . ' - ' . $hours['closes'];
          }
          $locResult['openingHours'] = $opening;
          $finalResult[] = nl2br(Yaml::dump($locResult));
        }
      }

      $form['location_results']['#markup'] = implode('<br>', $finalResult);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * Custom ajax submit handler for the form. Returns location results.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    // Return the location results element of the form.
    return $form['location_results'];
  }

}
