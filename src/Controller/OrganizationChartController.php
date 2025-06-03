<?php

namespace Drupal\organization_chart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for organization chart pages.
 */
class OrganizationChartController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new OrganizationChartController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Lists all organization charts.
   *
   * @return array
   *   A render array.
   */
  public function chartsList() {
    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'created' => $this->t('Created'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];
    $charts = $this->database->select('organization_charts', 'oc')
      ->fields('oc')
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll();

    foreach ($charts as $chart) {
      $operations = [
        Link::createFromRoute($this->t('Edit'), 'organization_chart.chart_edit', ['chart_id' => $chart->id]),
        Link::createFromRoute($this->t('Builder'), 'organization_chart.chart_builder', ['chart_id' => $chart->id]),
        Link::createFromRoute($this->t('Delete'), 'organization_chart.chart_delete', ['chart_id' => $chart->id]),
      ];

      $rows[] = [
        'id' => $chart->id,
        'name' => $chart->name,
        'created' => \Drupal::service('date.formatter')->format($chart->created, 'short'),
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('organization_chart.chart_edit', ['chart_id' => $chart->id]),
              ],
              'builder' => [
                'title' => $this->t('Builder'),
                'url' => Url::fromRoute('organization_chart.chart_builder', ['chart_id' => $chart->id]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('organization_chart.chart_delete', ['chart_id' => $chart->id]),
              ],
            ],
          ],
        ],
      ];
    }

    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No organization charts available. <a href="@add-url">Add one now</a>.', [
        '@add-url' => Url::fromRoute('organization_chart.chart_add')->toString(),
      ]),
    ];

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add new organization chart'),
      '#url' => Url::fromRoute('organization_chart.chart_add'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => -10,
    ];

    return $build;
  }

  /**
   * Lists all organization chart themes.
   *
   * @return array
   *   A render array.
   */
  public function themesList() {
    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'created' => $this->t('Created'),
      'operations' => $this->t('Operations'),
    ];

    $rows = [];
    $themes = $this->database->select('organization_chart_themes', 'oct')
      ->fields('oct')
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll();

    foreach ($themes as $theme) {
      $rows[] = [
        'id' => $theme->id,
        'name' => $theme->name,
        'created' => \Drupal::service('date.formatter')->format($theme->created, 'short'),
        'operations' => [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('organization_chart.theme_edit', ['theme_id' => $theme->id]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('organization_chart.theme_delete', ['theme_id' => $theme->id]),
              ],
            ],
          ],
        ],
      ];
    }

    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No themes available. <a href="@add-url">Add one now</a>.', [
        '@add-url' => Url::fromRoute('organization_chart.theme_add')->toString(),
      ]),
    ];

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add new theme'),
      '#url' => Url::fromRoute('organization_chart.theme_add'),
      '#attributes' => ['class' => ['button', 'button--primary']],
      '#weight' => -10,
    ];

    return $build;
  }

  /**
   * Chart builder interface.
   *
   * @param int $chart_id
   *   The chart ID.
   *
   * @return array
   *   A render array.
   */
  public function chartBuilder($chart_id) {
    $chart = $this->database->select('organization_charts', 'oc')
      ->fields('oc')
      ->condition('id', $chart_id)
      ->execute()
      ->fetchObject();

    if (!$chart) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Get chart elements.
    $elements = $this->database->select('organization_chart_elements', 'oce')
      ->fields('oce')
      ->condition('chart_id', $chart_id)
      ->orderBy('weight')
      ->execute()
      ->fetchAll();

    // Get available themes.
    $themes = $this->database->select('organization_chart_themes', 'oct')
      ->fields('oct')
      ->execute()
      ->fetchAllKeyed(0, 1);

    $build = [
      '#theme' => 'organization_chart_builder',
      '#chart' => $chart,
      '#elements' => $elements,
      '#themes' => $themes,
      '#attached' => [
        'library' => [
          'organization_chart/builder',
        ],
        'drupalSettings' => [
          'organizationChart' => [
            'chartId' => $chart_id,
            'elements' => array_map(function($element) {
              return (array) $element;
            }, $elements),
            'themes' => $themes,
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Preview a chart.
   *
   * @param int $chart_id
   *   The chart ID.
   *
   * @return array
   *   A render array.
   */
  public function previewChart($chart_id) {
    $service = \Drupal::service('organization_chart.service');

    $render_array = $service->renderChart($chart_id, NULL, [
      'show_title' => TRUE,
      'show_controls' => TRUE,
      'enable_fullscreen' => TRUE,
      'max_width' => '100%',
      'max_height' => 'auto',
    ]);

    if (!$render_array) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    // Add preview-specific styling.
    $render_array['#attached']['html_head'][] = [
      [
        '#tag' => 'style',
        '#value' => '
          body { margin: 0; padding: 20px; background: #f5f5f5; }
          .organization-chart { max-width: none; }
        ',
      ],
      'organization_chart_preview_css',
    ];

    return $render_array;
  }

  /**
   * Export chart data.
   *
   * @param int $chart_id
   *   The chart ID.
   * @param string $format
   *   Export format (json, csv, xml).
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The export response.
   */
  public function exportChart($chart_id, $format = 'json') {
    $chart = $this->database->select('organization_charts', 'oc')
      ->fields('oc')
      ->condition('id', $chart_id)
      ->execute()
      ->fetchObject();

    if (!$chart) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $elements = $this->database->select('organization_chart_elements', 'oce')
      ->fields('oce')
      ->condition('chart_id', $chart_id)
      ->orderBy('weight')
      ->execute()
      ->fetchAll();

    $export_data = [
      'chart' => (array) $chart,
      'elements' => array_map(function($element) {
        return (array) $element;
      }, $elements),
      'exported_at' => date('Y-m-d H:i:s'),
      'exported_by' => \Drupal::currentUser()->getDisplayName(),
    ];

    switch ($format) {
      case 'json':
        return $this->exportAsJson($export_data, $chart->name);

      case 'csv':
        return $this->exportAsCsv($export_data, $chart->name);

      case 'xml':
        return $this->exportAsXml($export_data, $chart->name);

      default:
        throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid export format');
    }
  }

  /**
   * Export data as JSON.
   */
  private function exportAsJson($data, $chart_name) {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $filename = $this->sanitizeFilename($chart_name) . '_' . date('Y-m-d') . '.json';

    $response = new \Symfony\Component\HttpFoundation\Response($json);
    $response->headers->set('Content-Type', 'application/json');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

  /**
   * Export data as CSV.
   */
  private function exportAsCsv($data, $chart_name) {
    $filename = $this->sanitizeFilename($chart_name) . '_' . date('Y-m-d') . '.csv';

    $output = fopen('php://temp', 'r+');

    // Write header.
    fputcsv($output, [
      'Element ID', 'Parent ID', 'Title', 'Description',
      'Image URL', 'Link URL', 'Position X', 'Position Y', 'Weight'
    ]);

    // Write elements.
    foreach ($data['elements'] as $element) {
      fputcsv($output, [
        $element['id'],
        $element['parent_id'],
        $element['title'],
        $element['description'],
        $element['image_url'],
        $element['link_url'],
        $element['position_x'],
        $element['position_y'],
        $element['weight'],
      ]);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    $response = new \Symfony\Component\HttpFoundation\Response($csv);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

  /**
   * Export data as XML.
   */
  private function exportAsXml($data, $chart_name) {
    $xml = new \SimpleXMLElement('<organization_chart></organization_chart>');

    // Add chart info.
    $chart_info = $xml->addChild('chart_info');
    foreach ($data['chart'] as $key => $value) {
      $chart_info->addChild($key, htmlspecialchars($value));
    }

    // Add elements.
    $elements_xml = $xml->addChild('elements');
    foreach ($data['elements'] as $element) {
      $element_xml = $elements_xml->addChild('element');
      foreach ($element as $key => $value) {
        $element_xml->addChild($key, htmlspecialchars($value));
      }
    }

    $filename = $this->sanitizeFilename($chart_name) . '_' . date('Y-m-d') . '.xml';

    $response = new \Symfony\Component\HttpFoundation\Response($xml->asXML());
    $response->headers->set('Content-Type', 'application/xml');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

  /**
   * Sanitizes filename for export.
   */
  private function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
  }

  /**
   * Dashboard page with statistics.
   *
   * @return array
   *   A render array.
   */
  public function dashboard() {
    // Get statistics.
    $total_charts = $this->database->select('organization_charts', 'oc')
      ->countQuery()
      ->execute()
      ->fetchField();

    $total_elements = $this->database->select('organization_chart_elements', 'oce')
      ->countQuery()
      ->execute()
      ->fetchField();

    $total_themes = $this->database->select('organization_chart_themes', 'oct')
      ->countQuery()
      ->execute()
      ->fetchField();

    // Get recent charts.
    $recent_charts = $this->database->select('organization_charts', 'oc')
      ->fields('oc', ['id', 'name', 'created'])
      ->orderBy('created', 'DESC')
      ->range(0, 5)
      ->execute()
      ->fetchAll();

    $build = [
      '#theme' => 'organization_chart_dashboard',
      '#statistics' => [
        'total_charts' => $total_charts,
        'total_elements' => $total_elements,
        'total_themes' => $total_themes,
      ],
      '#recent_charts' => $recent_charts,
      '#attached' => [
        'library' => ['organization_chart/admin'],
      ],
    ];

    return $build;
  }

  /**
   * Help page.
   *
   * @return array
   *   A render array.
   */
  public function helpPage() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['organization-chart-help']],
    ];

    $build['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('The Organization Chart module allows you to create and display hierarchical organizational charts on your website.'),
    ];

    $build['getting_started'] = [
      '#type' => 'details',
      '#title' => $this->t('Getting Started'),
      '#open' => TRUE,
    ];

    $build['getting_started']['content'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('Go to <a href="@charts">Structure > Organization Charts</a> to manage your charts.', [
          '@charts' => Url::fromRoute('organization_chart.charts_list')->toString(),
        ]),
        $this->t('Create themes at <a href="@themes">Chart Themes</a> to customize the appearance.', [
          '@themes' => Url::fromRoute('organization_chart.themes_list')->toString(),
        ]),
        $this->t('Use the visual builder to create your organizational structure.'),
        $this->t('Display charts using blocks or shortcodes like <code>[org_chart chart_id=1]</code>.'),
      ],
    ];

    $build['shortcodes'] = [
      '#type' => 'details',
      '#title' => $this->t('Shortcode Reference'),
      '#open' => FALSE,
    ];

    $build['shortcodes']['content'] = [
      '#markup' => '
        <h4>' . $this->t('Basic Usage') . '</h4>
        <pre><code>[org_chart chart_id=1]</code></pre>

        <h4>' . $this->t('With Theme') . '</h4>
        <pre><code>[org_chart chart_id=1 theme_id=2]</code></pre>

        <h4>' . $this->t('Custom Options') . '</h4>
        <pre><code>[org_chart chart_id=1 show_title=0 show_controls=0 max_width=800px]</code></pre>

        <h4>' . $this->t('Available Parameters') . '</h4>
        <ul>
          <li><strong>chart_id</strong> (required): Chart ID to display</li>
          <li><strong>theme_id</strong> (optional): Theme ID to use</li>
          <li><strong>show_title</strong> (optional): Show chart title (1 or 0)</li>
          <li><strong>show_controls</strong> (optional): Show zoom controls (1 or 0)</li>
          <li><strong>enable_fullscreen</strong> (optional): Enable fullscreen (1 or 0)</li>
          <li><strong>max_width</strong> (optional): Maximum width (e.g., 100%, 800px)</li>
          <li><strong>max_height</strong> (optional): Maximum height (e.g., 600px, auto)</li>
        </ul>
      ',
    ];

    return $build;
  }
}
