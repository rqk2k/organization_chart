<?php

namespace Drupal\organization_chart\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Organization Chart' block.
 *
 * @Block(
 *   id = "organization_chart_block",
 *   admin_label = @Translation("Organization Chart"),
 *   category = @Translation("Content")
 * )
 */
class OrganizationChartBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new OrganizationChartBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    // Get available charts.
    $charts = $this->database->select('organization_charts', 'oc')
      ->fields('oc', ['id', 'name'])
      ->execute()
      ->fetchAllKeyed();

    $form['chart_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Chart'),
      '#description' => $this->t('Choose which organization chart to display.'),
      '#options' => $charts,
      '#default_value' => $config['chart_id'] ?? '',
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select a chart -'),
    ];

    // Get available themes.
    $themes = $this->database->select('organization_chart_themes', 'oct')
      ->fields('oct', ['id', 'name'])
      ->execute()
      ->fetchAllKeyed();

    $form['theme_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Theme'),
      '#description' => $this->t('Choose which theme to use for this chart display.'),
      '#options' => $themes,
      '#default_value' => $config['theme_id'] ?? '',
      '#empty_option' => $this->t('- Use default theme -'),
    ];

    $form['display_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Display Options'),
      '#open' => TRUE,
    ];

    $form['display_options']['show_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show chart title'),
      '#default_value' => $config['show_title'] ?? TRUE,
    ];

    $form['display_options']['show_controls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show zoom controls'),
      '#default_value' => $config['show_controls'] ?? TRUE,
    ];

    $form['display_options']['enable_fullscreen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable fullscreen button'),
      '#default_value' => $config['enable_fullscreen'] ?? TRUE,
    ];

    $form['display_options']['max_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum width'),
      '#description' => $this->t('Maximum width of the chart (e.g., 100%, 800px).'),
      '#default_value' => $config['max_width'] ?? '100%',
      '#size' => 20,
    ];

    $form['display_options']['max_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Maximum height'),
      '#description' => $this->t('Maximum height of the chart (e.g., 600px, auto).'),
      '#default_value' => $config['max_height'] ?? 'auto',
      '#size' => 20,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();

    $this->configuration['chart_id'] = $values['chart_id'];
    $this->configuration['theme_id'] = $values['theme_id'];
    $this->configuration['show_title'] = $values['display_options']['show_title'];
    $this->configuration['show_controls'] = $values['display_options']['show_controls'];
    $this->configuration['enable_fullscreen'] = $values['display_options']['enable_fullscreen'];
    $this->configuration['max_width'] = $values['display_options']['max_width'];
    $this->configuration['max_height'] = $values['display_options']['max_height'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $chart_id = $config['chart_id'] ?? NULL;

    if (!$chart_id) {
      return [
        '#markup' => $this->t('No chart selected.'),
      ];
    }

    // Get chart data.
    $chart = $this->database->select('organization_charts', 'oc')
      ->fields('oc')
      ->condition('id', $chart_id)
      ->execute()
      ->fetchObject();

    if (!$chart) {
      return [
        '#markup' => $this->t('Chart not found.'),
      ];
    }

    // Get chart elements.
    $elements = $this->database->select('organization_chart_elements', 'oce')
      ->fields('oce')
      ->condition('chart_id', $chart_id)
      ->orderBy('weight')
      ->execute()
      ->fetchAll();

    // Get theme.
    $theme_id = $config['theme_id'] ?: \Drupal::config('organization_chart.settings')->get('default_theme');
    $theme = NULL;

    if ($theme_id) {
      $theme = $this->database->select('organization_chart_themes', 'oct')
        ->fields('oct')
        ->condition('id', $theme_id)
        ->execute()
        ->fetchObject();
    }

    $build = [
      '#theme' => 'organization_chart_display',
      '#chart' => $chart,
      '#elements' => $elements,
      '#theme_settings' => $theme ? json_decode($theme->settings, TRUE) : [],
      '#config' => $config,
      '#attached' => [
        'library' => [
          'organization_chart/display',
        ],
        'drupalSettings' => [
          'organizationChart' => [
            'charts' => [
              $chart_id => [
                'id' => $chart_id,
                'name' => $chart->name,
                'elements' => array_map(function($element) {
                  return (array) $element;
                }, $elements),
                'theme' => $theme ? json_decode($theme->settings, TRUE) : [],
                'config' => $config,
              ],
            ],
          ],
        ],
      ],
      '#cache' => [
        'tags' => [
          'organization_chart:' . $chart_id,
          'organization_chart_theme:' . $theme_id,
        ],
        'contexts' => ['user.permissions'],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'chart_id' => '',
      'theme_id' => '',
      'show_title' => TRUE,
      'show_controls' => TRUE,
      'enable_fullscreen' => TRUE,
      'max_width' => '100%',
      'max_height' => 'auto',
    ];
  }
}
