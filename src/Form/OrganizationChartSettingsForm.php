<?php

namespace Drupal\organization_chart\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for organization chart settings.
 */
class OrganizationChartSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'organization_chart_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['organization_chart.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('organization_chart.settings');

    $form['#attributes']['class'][] = 'organization-chart-settings-form';

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];

    $form['general']['default_image'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Default placeholder image'),
      '#description' => $this->t('Default image to show when no image is uploaded for an element.'),
      '#default_value' => $config->get('default_image') ? [$config->get('default_image')] : [],
      '#upload_location' => 'public://organization_chart/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg gif svg'],
        'file_validate_size' => [2097152], // 2MB
      ],
    ];

    $form['general']['cache_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable caching'),
      '#description' => $this->t('Cache rendered organization charts for better performance.'),
      '#default_value' => $config->get('cache_enabled') ?? TRUE,
    ];

    $form['general']['cache_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache max age'),
      '#description' => $this->t('Maximum age of cached charts in seconds.'),
      '#default_value' => $config->get('cache_max_age') ?? 3600,
      '#min' => 300,
      '#max' => 86400,
      '#field_suffix' => 'seconds',
      '#states' => [
        'visible' => [
          ':input[name="cache_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['general']['lazy_loading'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable lazy loading'),
      '#description' => $this->t('Load chart images only when they become visible.'),
      '#default_value' => $config->get('lazy_loading') ?? TRUE,
    ];

    $form['general']['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug mode'),
      '#description' => $this->t('Show debug information and verbose logging.'),
      '#default_value' => $config->get('debug_mode') ?? FALSE,
    ];

    $form['permissions'] = [
      '#type' => 'details',
      '#title' => $this->t('User Permissions'),
      '#open' => TRUE,
    ];

    $form['permissions']['allow_user_charts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow users to create charts'),
      '#description' => $this->t('Allow non-admin users to create their own organization charts.'),
      '#default_value' => $config->get('allow_user_charts') ?? FALSE,
    ];

    $form['permissions']['max_charts_per_user'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum charts per user'),
      '#description' => $this->t('Maximum number of charts a user can create (0 for unlimited).'),
      '#default_value' => $config->get('max_charts_per_user') ?? 0,
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':input[name="allow_user_charts"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['permissions']['max_elements_per_chart'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum elements per chart'),
      '#description' => $this->t('Maximum number of elements allowed in a single chart (0 for unlimited).'),
      '#default_value' => $config->get('max_elements_per_chart') ?? 0,
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':input[name="allow_user_charts"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['permissions']['allowed_file_types'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed image file types'),
      '#description' => $this->t('Comma-separated list of allowed file extensions.'),
      '#default_value' => $config->get('allowed_file_types') ?? 'jpg,jpeg,png,gif,webp',
      '#states' => [
        'visible' => [
          ':input[name="allow_user_charts"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['permissions']['max_file_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum file size'),
      '#description' => $this->t('Maximum file size for image uploads in MB.'),
      '#default_value' => $config->get('max_file_size') ?? 2,
      '#min' => 1,
      '#max' => 10,
      '#field_suffix' => 'MB',
      '#states' => [
        'visible' => [
          ':input[name="allow_user_charts"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['display'] = [
      '#type' => 'details',
      '#title' => $this->t('Display Settings'),
      '#open' => TRUE,
    ];

    $form['display']['default_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Default theme'),
      '#description' => $this->t('Default theme to use for new charts.'),
      '#default_value' => $config->get('default_theme') ?? 1,
      '#options' => $this->getThemeOptions(),
    ];

    $form['display']['animation_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable animations'),
      '#description' => $this->t('Add smooth animations when charts load and change.'),
      '#default_value' => $config->get('animation_enabled') ?? TRUE,
    ];

    $form['display']['animation_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Animation duration'),
      '#description' => $this->t('Duration of animations in milliseconds.'),
      '#default_value' => $config->get('animation_duration') ?? 300,
      '#min' => 100,
      '#max' => 2000,
      '#field_suffix' => 'ms',
      '#states' => [
        'visible' => [
          ':input[name="animation_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['display']['responsive_breakpoint'] = [
      '#type' => 'number',
      '#title' => $this->t('Responsive breakpoint'),
      '#description' => $this->t('Screen width in pixels below which charts become mobile-responsive.'),
      '#default_value' => $config->get('responsive_breakpoint') ?? 768,
      '#min' => 320,
      '#max' => 1200,
      '#field_suffix' => 'px',
    ];

    $form['display']['default_chart_width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default chart width'),
      '#description' => $this->t('Default width for charts (e.g., 100%, 800px).'),
      '#default_value' => $config->get('default_chart_width') ?? '100%',
      '#size' => 20,
    ];

    $form['display']['default_chart_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default chart height'),
      '#description' => $this->t('Default height for charts (e.g., auto, 600px).'),
      '#default_value' => $config->get('default_chart_height') ?? 'auto',
      '#size' => 20,
    ];

    $form['performance'] = [
      '#type' => 'details',
      '#title' => $this->t('Performance Settings'),
      '#open' => FALSE,
    ];

    $form['performance']['optimize_images'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Optimize uploaded images'),
      '#description' => $this->t('Automatically optimize uploaded images for web display.'),
      '#default_value' => $config->get('optimize_images') ?? TRUE,
    ];

    $form['performance']['image_quality'] = [
      '#type' => 'number',
      '#title' => $this->t('Image quality'),
      '#description' => $this->t('JPEG quality for optimized images (1-100).'),
      '#default_value' => $config->get('image_quality') ?? 85,
      '#min' => 1,
      '#max' => 100,
      '#states' => [
        'visible' => [
          ':input[name="optimize_images"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['performance']['preload_charts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Preload chart data'),
      '#description' => $this->t('Preload chart data to improve initial load times.'),
      '#default_value' => $config->get('preload_charts') ?? FALSE,
    ];

    $form['performance']['cdn_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable CDN for images'),
      '#description' => $this->t('Use CDN URLs for chart images when available.'),
      '#default_value' => $config->get('cdn_enabled') ?? FALSE,
    ];

    $form['performance']['cdn_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('CDN base URL'),
      '#description' => $this->t('Base URL for CDN image delivery.'),
      '#default_value' => $config->get('cdn_base_url') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="cdn_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced Settings'),
      '#open' => FALSE,
    ];

    $form['advanced']['custom_css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Global custom CSS'),
      '#description' => $this->t('Add custom CSS to style organization charts globally.'),
      '#default_value' => $config->get('custom_css') ?? '',
      '#rows' => 10,
    ];

    $form['advanced']['custom_js'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Global custom JavaScript'),
      '#description' => $this->t('Add custom JavaScript for enhanced functionality.'),
      '#default_value' => $config->get('custom_js') ?? '',
      '#rows' => 10,
    ];

    $form['advanced']['api_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable REST API'),
      '#description' => $this->t('Enable REST API endpoints for chart data.'),
      '#default_value' => $config->get('api_enabled') ?? FALSE,
    ];

    $form['advanced']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('API key for REST API access (leave empty to disable authentication).'),
      '#default_value' => $config->get('api_key') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="api_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['advanced']['webhook_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Webhook URL'),
      '#description' => $this->t('URL to notify when charts are created or updated.'),
      '#default_value' => $config->get('webhook_url') ?? '',
    ];

    $form['integration'] = [
      '#type' => 'details',
      '#title' => $this->t('Third-party Integrations'),
      '#open' => FALSE,
    ];

    $form['integration']['google_analytics'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Google Analytics tracking'),
      '#description' => $this->t('Track chart interactions with Google Analytics.'),
      '#default_value' => $config->get('google_analytics') ?? FALSE,
    ];

    $form['integration']['ga_tracking_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Analytics tracking ID'),
      '#description' => $this->t('Your Google Analytics tracking ID (e.g., GA-XXXXX-X).'),
      '#default_value' => $config->get('ga_tracking_id') ?? '',
      '#states' => [
        'visible' => [
          ':input[name="google_analytics"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['integration']['export_formats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available export formats'),
      '#description' => $this->t('Select which export formats to make available.'),
      '#options' => [
        'json' => $this->t('JSON'),
        'csv' => $this->t('CSV'),
        'xml' => $this->t('XML'),
        'pdf' => $this->t('PDF'),
        'png' => $this->t('PNG Image'),
        'svg' => $this->t('SVG Image'),
      ],
      '#default_value' => $config->get('export_formats') ?? ['json', 'csv', 'xml'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate cache max age.
    $cache_max_age = $form_state->getValue('cache_max_age');
    if ($cache_max_age < 300) {
      $form_state->setErrorByName('cache_max_age', $this->t('Cache max age must be at least 300 seconds.'));
    }

    // Validate file types.
    $allowed_types = $form_state->getValue('allowed_file_types');
    if ($allowed_types) {
      $types = array_map('trim', explode(',', $allowed_types));
      $valid_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

      foreach ($types as $type) {
        if (!in_array(strtolower($type), $valid_types)) {
          $form_state->setErrorByName('allowed_file_types', $this->t('Invalid file type: @type', ['@type' => $type]));
        }
      }
    }

    // Validate dimensions.
    $default_width = $form_state->getValue('default_chart_width');
    if ($default_width && !preg_match('/^(\d+(%|px)|auto)$/', $default_width)) {
      $form_state->setErrorByName('default_chart_width', $this->t('Invalid width format. Use px, %, or auto.'));
    }

    $default_height = $form_state->getValue('default_chart_height');
    if ($default_height && !preg_match('/^(\d+(%|px)|auto)$/', $default_height)) {
      $form_state->setErrorByName('default_chart_height', $this->t('Invalid height format. Use px, %, or auto.'));
    }

    // Validate CDN URL.
    $cdn_url = $form_state->getValue('cdn_base_url');
    if ($form_state->getValue('cdn_enabled') && empty($cdn_url)) {
      $form_state->setErrorByName('cdn_base_url', $this->t('CDN base URL is required when CDN is enabled.'));
    }

    // Validate Google Analytics ID.
    $ga_id = $form_state->getValue('ga_tracking_id');
    if ($form_state->getValue('google_analytics') && $ga_id && !preg_match('/^(GA|G)-[A-Z0-9-]+$/i', $ga_id)) {
      $form_state->setErrorByName('ga_tracking_id', $this->t('Invalid Google Analytics tracking ID format.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('organization_chart.settings');

    // Handle file upload.
    $default_image = $form_state->getValue('default_image');
    if (!empty($default_image[0])) {
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($default_image[0]);
      if ($file) {
        $file->setPermanent();
        $file->save();
        \Drupal::service('file.usage')->add($file, 'organization_chart', 'config', 1);
        $config->set('default_image', $file->id());
      }
    }
    else {
      $config->set('default_image', NULL);
    }

    $config
      ->set('cache_enabled', $form_state->getValue('cache_enabled'))
      ->set('cache_max_age', $form_state->getValue('cache_max_age'))
      ->set('lazy_loading', $form_state->getValue('lazy_loading'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->set('allow_user_charts', $form_state->getValue('allow_user_charts'))
      ->set('max_charts_per_user', $form_state->getValue('max_charts_per_user'))
      ->set('max_elements_per_chart', $form_state->getValue('max_elements_per_chart'))
      ->set('allowed_file_types', $form_state->getValue('allowed_file_types'))
      ->set('max_file_size', $form_state->getValue('max_file_size'))
      ->set('default_theme', $form_state->getValue('default_theme'))
      ->set('animation_enabled', $form_state->getValue('animation_enabled'))
      ->set('animation_duration', $form_state->getValue('animation_duration'))
      ->set('responsive_breakpoint', $form_state->getValue('responsive_breakpoint'))
      ->set('default_chart_width', $form_state->getValue('default_chart_width'))
      ->set('default_chart_height', $form_state->getValue('default_chart_height'))
      ->set('optimize_images', $form_state->getValue('optimize_images'))
      ->set('image_quality', $form_state->getValue('image_quality'))
      ->set('preload_charts', $form_state->getValue('preload_charts'))
      ->set('cdn_enabled', $form_state->getValue('cdn_enabled'))
      ->set('cdn_base_url', $form_state->getValue('cdn_base_url'))
      ->set('custom_css', $form_state->getValue('custom_css'))
      ->set('custom_js', $form_state->getValue('custom_js'))
      ->set('api_enabled', $form_state->getValue('api_enabled'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('webhook_url', $form_state->getValue('webhook_url'))
      ->set('google_analytics', $form_state->getValue('google_analytics'))
      ->set('ga_tracking_id', $form_state->getValue('ga_tracking_id'))
      ->set('export_formats', array_filter($form_state->getValue('export_formats')))
      ->save();

    // Clear all organization chart caches.
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['organization_chart']);

    parent::submitForm($form, $form_state);
  }

  /**
   * Gets available theme options.
   *
   * @return array
   *   Theme options.
   */
  private function getThemeOptions() {
    $options = [];

    $themes = \Drupal::database()->select('organization_chart_themes', 'oct')
      ->fields('oct', ['id', 'name'])
      ->execute()
      ->fetchAllKeyed();

    foreach ($themes as $id => $name) {
      $options[$id] = $name;
    }

    return $options ?: [1 => $this->t('Default Theme')];
  }
}
