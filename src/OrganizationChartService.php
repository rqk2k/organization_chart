<?php

namespace Drupal\organization_chart;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Service for managing organization charts.
 */
class OrganizationChartService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new OrganizationChartService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * Renders an organization chart.
   *
   * @param int $chart_id
   *   The chart ID.
   * @param int|null $theme_id
   *   The theme ID (optional).
   * @param array $options
   *   Display options.
   *
   * @return array|null
   *   Render array or NULL if chart not found.
   */
  public function renderChart($chart_id, $theme_id = NULL, array $options = []) {
    // Get chart data.
    $chart = $this->database->select('organization_charts', 'oc')
      ->fields('oc')
      ->condition('id', $chart_id)
      ->execute()
      ->fetchObject();

    if (!$chart) {
      return NULL;
    }

    // Get chart elements.
    $elements = $this->database->select('organization_chart_elements', 'oce')
      ->fields('oce')
      ->condition('chart_id', $chart_id)
      ->orderBy('weight')
      ->execute()
      ->fetchAll();

    // Get theme.
    if (!$theme_id) {
      $theme_id = $this->configFactory->get('organization_chart.settings')->get('default_theme');
    }

    $theme = NULL;
    if ($theme_id) {
      $theme = $this->database->select('organization_chart_themes', 'oct')
        ->fields('oct')
        ->condition('id', $theme_id)
        ->execute()
        ->fetchObject();
    }

    // Default options.
    $default_options = [
      'show_title' => TRUE,
      'show_controls' => TRUE,
      'enable_fullscreen' => TRUE,
      'max_width' => '100%',
      'max_height' => 'auto',
    ];

    $options = array_merge($default_options, $options);

    $build = [
      '#theme' => 'organization_chart_display',
      '#chart' => $chart,
      '#elements' => $elements,
      '#theme_settings' => $theme ? json_decode($theme->settings, TRUE) : [],
      '#config' => $options,
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
                'config' => $options,
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
   * Gets all available charts.
   *
   * @return array
   *   Array of charts keyed by ID.
   */
  public function getCharts() {
    return $this->database->select('organization_charts', 'oc')
      ->fields('oc')
      ->orderBy('name')
      ->execute()
      ->fetchAllAssoc('id');
  }

  /**
   * Gets all available themes.
   *
   * @return array
   *   Array of themes keyed by ID.
   */
  public function getThemes() {
    return $this->database->select('organization_chart_themes', 'oct')
      ->fields('oct')
      ->orderBy('name')
      ->execute()
      ->fetchAllAssoc('id');
  }

  /**
   * Gets chart elements in hierarchical structure.
   *
   * @param int $chart_id
   *   The chart ID.
   *
   * @return array
   *   Hierarchical array of elements.
   */
  public function getChartHierarchy($chart_id) {
    $elements = $this->database->select('organization_chart_elements', 'oce')
      ->fields('oce')
      ->condition('chart_id', $chart_id)
      ->orderBy('weight')
      ->execute()
      ->fetchAllAssoc('id');

    return $this->buildHierarchy($elements);
  }

  /**
   * Builds hierarchical structure from flat array.
   *
   * @param array $elements
   *   Flat array of elements.
   * @param int|null $parent_id
   *   Parent ID to build from.
   *
   * @return array
   *   Hierarchical array.
   */
  private function buildHierarchy(array $elements, $parent_id = NULL) {
    $hierarchy = [];

    foreach ($elements as $element) {
      if ($element->parent_id == $parent_id) {
        $element->children = $this->buildHierarchy($elements, $element->id);
        $hierarchy[] = $element;
      }
    }

    return $hierarchy;
  }

  /**
   * Processes shortcode-like tokens in content.
   *
   * @param string $content
   *   The content to process.
   *
   * @return string
   *   Processed content.
   */
  public function processTokens($content) {
    // Pattern: [org_chart chart_id=1 theme_id=2]
    $pattern = '/\[org_chart\s+([^\]]*)\]/';

    return preg_replace_callback($pattern, function($matches) {
      $attributes = $this->parseShortcodeAttributes($matches[1]);

      $chart_id = $attributes['chart_id'] ?? NULL;
      $theme_id = $attributes['theme_id'] ?? NULL;

      if (!$chart_id) {
        return '[Invalid chart ID]';
      }

      $options = [
        'show_title' => isset($attributes['show_title']) ? (bool) $attributes['show_title'] : TRUE,
        'show_controls' => isset($attributes['show_controls']) ? (bool) $attributes['show_controls'] : TRUE,
        'enable_fullscreen' => isset($attributes['enable_fullscreen']) ? (bool) $attributes['enable_fullscreen'] : TRUE,
        'max_width' => $attributes['max_width'] ?? '100%',
        'max_height' => $attributes['max_height'] ?? 'auto',
      ];

      $render_array = $this->renderChart($chart_id, $theme_id, $options);

      if (!$render_array) {
        return '[Chart not found]';
      }

      return $this->renderer->render($render_array);
    }, $content);
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

  /**
   * Duplicates a chart.
   *
   * @param int $chart_id
   *   The chart ID to duplicate.
   * @param string $new_name
   *   New name for the duplicated chart.
   *
   * @return int|bool
   *   New chart ID or FALSE on failure.
   */
  public function duplicateChart($chart_id, $new_name) {
    try {
      // Get original chart.
      $original_chart = $this->database->select('organization_charts', 'oc')
        ->fields('oc')
        ->condition('id', $chart_id)
        ->execute()
        ->fetchObject();

      if (!$original_chart) {
        return FALSE;
      }

      $time = \Drupal::time()->getRequestTime();

      // Create new chart.
      $new_chart_id = $this->database->insert('organization_charts')
        ->fields([
          'name' => $new_name,
          'data' => $original_chart->data,
          'created' => $time,
          'updated' => $time,
        ])
        ->execute();

      // Get original elements.
      $original_elements = $this->database->select('organization_chart_elements', 'oce')
        ->fields('oce')
        ->condition('chart_id', $chart_id)
        ->execute()
        ->fetchAll();

      $element_mapping = [];

      // Duplicate elements.
      foreach ($original_elements as $element) {
        $new_element_id = $this->database->insert('organization_chart_elements')
          ->fields([
            'chart_id' => $new_chart_id,
            'parent_id' => NULL, // Will be updated later
            'title' => $element->title,
            'description' => $element->description,
            'image_url' => $element->image_url,
            'link_url' => $element->link_url,
            'position_x' => $element->position_x,
            'position_y' => $element->position_y,
            'theme_id' => $element->theme_id,
            'weight' => $element->weight,
          ])
          ->execute();

        $element_mapping[$element->id] = $new_element_id;
      }

      // Update parent relationships.
      foreach ($original_elements as $element) {
        if ($element->parent_id && isset($element_mapping[$element->parent_id])) {
          $this->database->update('organization_chart_elements')
            ->fields(['parent_id' => $element_mapping[$element->parent_id]])
            ->condition('id', $element_mapping[$element->id])
            ->execute();
        }
      }

      return $new_chart_id;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }
}
