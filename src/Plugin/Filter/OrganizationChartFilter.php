<?php

namespace Drupal\organization_chart\Plugin\Filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\organization_chart\OrganizationChartService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to convert organization chart shortcodes.
 *
 * @Filter(
 *   id = "organization_chart_shortcode",
 *   title = @Translation("Organization Chart Shortcodes"),
 *   description = @Translation("Converts [org_chart] shortcodes to organization charts."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 *   settings = {
 *     "cache_enabled" = TRUE,
 *   },
 *   weight = 0
 * )
 */
class OrganizationChartFilter extends FilterBase implements ContainerFactoryPluginInterface {

  /**
   * The organization chart service.
   *
   * @var \Drupal\organization_chart\OrganizationChartService
   */
  protected $organizationChartService;

  /**
   * Constructs a new OrganizationChartFilter object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\organization_chart\OrganizationChartService $organization_chart_service
   *   The organization chart service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, OrganizationChartService $organization_chart_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->organizationChartService = $organization_chart_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('organization_chart.service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $new_text = $this->organizationChartService->processTokens($text);

    $result = new FilterProcessResult($new_text);

    // Add cache tags for all charts referenced in the text.
    $cache_tags = [];
    if (preg_match_all('/\[org_chart\s+([^\]]*)\]/', $text, $matches)) {
      foreach ($matches[1] as $attributes) {
        $parsed_attributes = $this->parseShortcodeAttributes($attributes);
        if (!empty($parsed_attributes['chart_id'])) {
          $cache_tags[] = 'organization_chart:' . $parsed_attributes['chart_id'];
        }
        if (!empty($parsed_attributes['theme_id'])) {
          $cache_tags[] = 'organization_chart_theme:' . $parsed_attributes['theme_id'];
        }
      }
    }

    if (!empty($cache_tags)) {
      $result->addCacheTags($cache_tags);
    }

    // Cache for 1 hour if caching is enabled.
    if ($this->settings['cache_enabled']) {
      $result->setCacheMaxAge(3600);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    if ($long) {
      return $this->t('
        <p>You can embed organization charts using shortcodes:</p>
        <ul>
          <li><code>[org_chart chart_id=1]</code> - Display chart with ID 1</li>
          <li><code>[org_chart chart_id=1 theme_id=2]</code> - Display chart with specific theme</li>
          <li><code>[org_chart chart_id=1 show_title=0 show_controls=0]</code> - Display chart without title and controls</li>
        </ul>
        <p>Available parameters:</p>
        <ul>
          <li><strong>chart_id</strong> (required): The ID of the chart to display</li>
          <li><strong>theme_id</strong> (optional): The ID of the theme to use</li>
          <li><strong>show_title</strong> (optional): Show chart title (1 or 0)</li>
          <li><strong>show_controls</strong> (optional): Show zoom controls (1 or 0)</li>
          <li><strong>enable_fullscreen</strong> (optional): Enable fullscreen button (1 or 0)</li>
          <li><strong>max_width</strong> (optional): Maximum width (e.g., 100%, 800px)</li>
          <li><strong>max_height</strong> (optional): Maximum height (e.g., 600px, auto)</li>
        </ul>
      ');
    }
    else {
      return $this->t('You can embed organization charts using <code>[org_chart chart_id=1]</code> shortcodes.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['cache_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable caching'),
      '#description' => $this->t('Cache the rendered organization charts for better performance.'),
      '#default_value' => $this->settings['cache_enabled'],
    ];

    return $form;
  }

  /**
   * Parses shortcode attributes.
   *
   * @param string $attributes
   *   Attribute string.
   *
   * @return array
   *   Parsed attributes.
   */
  private function parseShortcodeAttributes($attributes) {
    $result = [];

    // Pattern for key="value" or key=value
    preg_match_all('/(\w+)=([\'"]?)([^\'">\s]*)\2/', $attributes, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
      $key = $match[1];
      $value = $match[3];

      // Convert numeric values
      if (is_numeric($value)) {
        $value = (int) $value;
      }

      $result[$key] = $value;
    }

    return $result;
  }
}
