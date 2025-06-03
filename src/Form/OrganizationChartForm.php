<?php

namespace Drupal\organization_chart\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding/editing organization charts.
 */
class OrganizationChartForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new OrganizationChartForm object.
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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'organization_chart_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $chart_id = NULL) {
    $chart = NULL;

    if ($chart_id) {
      $chart = $this->database->select('organization_charts', 'oc')
        ->fields('oc')
        ->condition('id', $chart_id)
        ->execute()
        ->fetchObject();
    }

    $form['#attributes']['class'][] = 'organization-chart-form';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Chart Name'),
      '#description' => $this->t('Enter a name for this organization chart.'),
      '#default_value' => $chart ? $chart->name : '',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Optional description for this chart.'),
      '#default_value' => $chart && $chart->data ? json_decode($chart->data, TRUE)['description'] ?? '' : '',
      '#rows' => 3,
    ];

    // Template selection for new charts.
    if (!$chart_id) {
      $form['template'] = [
        '#type' => 'select',
        '#title' => $this->t('Chart Template'),
        '#description' => $this->t('Choose a starting template for your chart.'),
        '#options' => [
          'blank' => $this->t('Blank Chart'),
          'basic_company' => $this->t('Basic Company Structure'),
          'department' => $this->t('Department Structure'),
          'project_team' => $this->t('Project Team'),
          'matrix' => $this->t('Matrix Organization'),
        ],
        '#default_value' => 'blank',
      ];

      $form['template_description'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'template-description'],
      ];

      $form['template_description']['content'] = [
        '#markup' => '<div class="template-preview">' . $this->t('Select a template to see preview') . '</div>',
      ];

      $form['#attached']['library'][] = 'organization_chart/admin';
      $form['#attached']['drupalSettings']['organizationChart']['templates'] = $this->getTemplateDescriptions();
    }

    // Chart settings.
    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Chart Settings'),
      '#open' => FALSE,
    ];

    // Get available themes.
    $themes = $this->database->select('organization_chart_themes', 'oct')
      ->fields('oct', ['id', 'name'])
      ->execute()
      ->fetchAllKeyed();

    $form['settings']['default_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Theme'),
      '#description' => $this->t('Default theme for this chart.'),
      '#options' => $themes,
      '#default_value' => $chart && $chart->data ? json_decode($chart->data, TRUE)['default_theme'] ?? '' : '',
      '#empty_option' => $this->t('- Use global default -'),
    ];

    $form['settings']['max_levels'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Levels'),
      '#description' => $this->t('Maximum number of hierarchy levels (0 for unlimited).'),
      '#default_value' => $chart && $chart->data ? json_decode($chart->data, TRUE)['max_levels'] ?? 0 : 0,
      '#min' => 0,
      '#max' => 20,
    ];

    $form['settings']['auto_layout'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automatic layout'),
      '#description' => $this->t('Automatically arrange elements when adding new ones.'),
      '#default_value' => $chart && $chart->data ? json_decode($chart->data, TRUE)['auto_layout'] ?? TRUE : TRUE,
    ];

    $form['settings']['allow_public_view'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow public viewing'),
      '#description' => $this->t('Allow anonymous users to view this chart.'),
      '#default_value' => $chart && $chart->data ? json_decode($chart->data, TRUE)['allow_public_view'] ?? FALSE : FALSE,
    ];

    // Advanced settings.
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['custom_css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom CSS'),
      '#description' => $this->t('Custom CSS rules for this specific chart.'),
      '#default_value' => $chart && $chart->data ? json_decode($chart->data, TRUE)['custom_css'] ?? '' : '',
      '#rows' => 5,
    ];

    $form['advanced']['custom_js'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom JavaScript'),
      '#description' => $this->t('Custom JavaScript for this specific chart.'),
      '#default_value' => $chart && $chart->data ? json_decode($chart->data, TRUE)['custom_js'] ?? '' : '',
      '#rows' => 5,
    ];

    $form['chart_id'] = [
      '#type' => 'value',
      '#value' => $chart_id,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $chart_id ? $this->t('Update Chart') : $this->t('Create Chart'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('organization_chart.charts_list'),
      '#attributes' => ['class' => ['button']],
    ];

    if ($chart_id) {
      $form['actions']['builder'] = [
        '#type' => 'link',
        '#title' => $this->t('Open Builder'),
        '#url' => Url::fromRoute('organization_chart.chart_builder', ['chart_id' => $chart_id]),
        '#attributes' => ['class' => ['button', 'button--action']],
      ];

      $form['actions']['duplicate'] = [
        '#type' => 'submit',
        '#value' => $this->t('Duplicate Chart'),
        '#submit' => ['::duplicateChart'],
        '#attributes' => ['class' => ['button']],
      ];

      $form['actions']['export'] = [
        '#type' => 'dropbutton',
        '#links' => [
          'json' => [
            'title' => $this->t('Export as JSON'),
            'url' => Url::fromRoute('organization_chart.export', [
              'chart_id' => $chart_id,
              'format' => 'json',
            ]),
          ],
          'csv' => [
            'title' => $this->t('Export as CSV'),
            'url' => Url::fromRoute('organization_chart.export', [
              'chart_id' => $chart_id,
              'format' => 'csv',
            ]),
          ],
          'xml' => [
            'title' => $this->t('Export as XML'),
            'url' => Url::fromRoute('organization_chart.export', [
              'chart_id' => $chart_id,
              'format' => 'xml',
            ]),
          ],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $chart_id = $form_state->getValue('chart_id');

    // Check for duplicate names.
    $query = $this->database->select('organization_charts', 'oc')
      ->fields('oc', ['id'])
      ->condition('name', $name);

    if ($chart_id) {
      $query->condition('id', $chart_id, '!=');
    }

    $existing = $query->execute()->fetchField();

    if ($existing) {
      $form_state->setErrorByName('name', $this->t('A chart with this name already exists.'));
    }

    // Validate CSS.
    $custom_css = $form_state->getValue(['advanced', 'custom_css']);
    if ($custom_css && !$this->validateCss($custom_css)) {
      $form_state->setErrorByName(['advanced', 'custom_css'], $this->t('Invalid CSS syntax detected.'));
    }

    // Validate JavaScript.
    $custom_js = $form_state->getValue(['advanced', 'custom_js']);
    if ($custom_js && !$this->validateJavaScript($custom_js)) {
      $form_state->setErrorByName(['advanced', 'custom_js'], $this->t('Invalid JavaScript syntax detected.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $description = $form_state->getValue('description');
    $chart_id = $form_state->getValue('chart_id');
    $template = $form_state->getValue('template');
    $time = \Drupal::time()->getRequestTime();

    $data = [
      'description' => $description,
      'default_theme' => $form_state->getValue(['settings', 'default_theme']),
      'max_levels' => $form_state->getValue(['settings', 'max_levels']),
      'auto_layout' => $form_state->getValue(['settings', 'auto_layout']),
      'allow_public_view' => $form_state->getValue(['settings', 'allow_public_view']),
      'custom_css' => $form_state->getValue(['advanced', 'custom_css']),
      'custom_js' => $form_state->getValue(['advanced', 'custom_js']),
    ];

    try {
      if ($chart_id) {
        // Update existing chart.
        $this->database->update('organization_charts')
          ->fields([
            'name' => $name,
            'data' => json_encode($data),
            'updated' => $time,
          ])
          ->condition('id', $chart_id)
          ->execute();

        // Clear caches.
        \Drupal::service('cache_tags.invalidator')->invalidateTags([
          'organization_chart:' . $chart_id
        ]);

        $this->messenger()->addMessage($this->t('Organization chart "@name" has been updated.', ['@name' => $name]));
      }
      else {
        // Create new chart.
        $chart_id = $this->database->insert('organization_charts')
          ->fields([
            'name' => $name,
            'data' => json_encode($data),
            'created' => $time,
            'updated' => $time,
          ])
          ->execute();

        // Create template elements.
        $this->createTemplateElements($chart_id, $template);

        $this->messenger()->addMessage($this->t('Organization chart "@name" has been created.', ['@name' => $name]));
      }

      $form_state->setRedirect('organization_chart.charts_list');
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while saving the chart: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Duplicate chart submit handler.
   */
  public function duplicateChart(array &$form, FormStateInterface $form_state) {
    $chart_id = $form_state->getValue('chart_id');
    $name = $form_state->getValue('name') . ' (Copy)';

    $service = \Drupal::service('organization_chart.service');
    $new_chart_id = $service->duplicateChart($chart_id, $name);

    if ($new_chart_id) {
      $this->messenger()->addMessage($this->t('Chart has been duplicated successfully.'));
      $form_state->setRedirect('organization_chart.chart_edit', ['chart_id' => $new_chart_id]);
    }
    else {
      $this->messenger()->addError($this->t('Failed to duplicate chart.'));
    }
  }

  /**
   * Creates template elements based on selected template.
   */
  private function createTemplateElements($chart_id, $template) {
    $templates = [
      'blank' => [],
      'basic_company' => [
        ['title' => 'CEO', 'description' => 'Chief Executive Officer', 'parent_id' => NULL, 'x' => 200, 'y' => 50],
        ['title' => 'CTO', 'description' => 'Chief Technology Officer', 'parent_id' => 1, 'x' => 100, 'y' => 150],
        ['title' => 'CFO', 'description' => 'Chief Financial Officer', 'parent_id' => 1, 'x' => 300, 'y' => 150],
        ['title' => 'Manager', 'description' => 'Development Manager', 'parent_id' => 2, 'x' => 100, 'y' => 250],
      ],
      'department' => [
        ['title' => 'Department Head', 'description' => 'Department Manager', 'parent_id' => NULL, 'x' => 200, 'y' => 50],
        ['title' => 'Team Lead A', 'description' => 'Team Leader', 'parent_id' => 1, 'x' => 100, 'y' => 150],
        ['title' => 'Team Lead B', 'description' => 'Team Leader', 'parent_id' => 1, 'x' => 300, 'y' => 150],
      ],
      'project_team' => [
        ['title' => 'Project Manager', 'description' => 'Project Manager', 'parent_id' => NULL, 'x' => 200, 'y' => 50],
        ['title' => 'Developer', 'description' => 'Software Developer', 'parent_id' => 1, 'x' => 100, 'y' => 150],
        ['title' => 'Designer', 'description' => 'UI/UX Designer', 'parent_id' => 1, 'x' => 300, 'y' => 150],
      ],
      'matrix' => [
        ['title' => 'Director', 'description' => 'Executive Director', 'parent_id' => NULL, 'x' => 200, 'y' => 50],
        ['title' => 'Functional Manager', 'description' => 'Functional Manager', 'parent_id' => 1, 'x' => 100, 'y' => 150],
        ['title' => 'Project Manager', 'description' => 'Project Manager', 'parent_id' => 1, 'x' => 300, 'y' => 150],
      ],
    ];

    if (!isset($templates[$template])) {
      return;
    }

    $element_ids = [];
    foreach ($templates[$template] as $index => $element) {
      $element_id = $this->database->insert('organization_chart_elements')
        ->fields([
          'chart_id' => $chart_id,
          'parent_id' => $element['parent_id'] ? $element_ids[$element['parent_id'] - 1] : NULL,
          'title' => $element['title'],
          'description' => $element['description'],
          'image_url' => '',
          'link_url' => '',
          'position_x' => $element['x'],
          'position_y' => $element['y'],
          'theme_id' => NULL,
          'weight' => $index,
        ])
        ->execute();

      $element_ids[] = $element_id;
    }
  }

  /**
   * Gets template descriptions for JavaScript.
   */
  private function getTemplateDescriptions() {
    return [
      'blank' => [
        'title' => $this->t('Blank Chart'),
        'description' => $this->t('Start with an empty chart and build your structure from scratch.'),
        'preview' => '/modules/organization_chart/images/template-blank.png',
      ],
      'basic_company' => [
        'title' => $this->t('Basic Company Structure'),
        'description' => $this->t('Traditional company hierarchy with CEO, CTO, CFO structure.'),
        'preview' => '/modules/organization_chart/images/template-company.png',
      ],
      'department' => [
        'title' => $this->t('Department Structure'),
        'description' => $this->t('Departmental organization with team leads.'),
        'preview' => '/modules/organization_chart/images/template-department.png',
      ],
      'project_team' => [
        'title' => $this->t('Project Team'),
        'description' => $this->t('Project-based team structure.'),
        'preview' => '/modules/organization_chart/images/template-project.png',
      ],
      'matrix' => [
        'title' => $this->t('Matrix Organization'),
        'description' => $this->t('Matrix organizational structure.'),
        'preview' => '/modules/organization_chart/images/template-matrix.png',
      ],
    ];
  }

  /**
   * Validates CSS syntax.
   */
  private function validateCss($css) {
    // Basic CSS validation - could be enhanced with a proper CSS parser.
    $css = trim($css);
    if (empty($css)) {
      return TRUE;
    }

    // Check for basic CSS structure.
    return preg_match('/^[^{}]*\{[^{}]*\}[^{}]*$/', $css) ||
           preg_match('/^[^{}]*\{[^{}]*\}(\s*[^{}]*\{[^{}]*\})*[^{}]*$/', $css);
  }

  /**
   * Validates JavaScript syntax.
   */
  private function validateJavaScript($js) {
    // Basic JavaScript validation - could be enhanced with a proper JS parser.
    $js = trim($js);
    if (empty($js)) {
      return TRUE;
    }

    // Check for dangerous functions.
    $dangerous_functions = ['eval', 'exec', 'system', 'shell_exec'];
    foreach ($dangerous_functions as $func) {
      if (strpos($js, $func) !== FALSE) {
        return FALSE;
      }
    }

    return TRUE;
  }
}
