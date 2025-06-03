<?php

namespace Drupal\organization_chart\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding/editing organization chart themes.
 */
class OrganizationChartThemeForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new OrganizationChartThemeForm object.
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
    return 'organization_chart_theme_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $theme_id = NULL) {
    $theme = NULL;
    $settings = [];

    if ($theme_id) {
      $theme = $this->database->select('organization_chart_themes', 'oct')
        ->fields('oct')
        ->condition('id', $theme_id)
        ->execute()
        ->fetchObject();

      if ($theme && $theme->settings) {
        $settings = json_decode($theme->settings, TRUE);
      }
    }

    // Default settings.
    $default_settings = [
      'general' => [
        'horizontal_scroll' => TRUE,
        'responsive' => TRUE,
        'zoom_enabled' => TRUE,
        'fullscreen_enabled' => TRUE,
        'popup_enabled' => TRUE,
      ],
      'line_style' => [
        'line_color' => '#cccccc',
        'line_width' => 2,
        'line_style' => 'solid',
      ],
      'item_style' => [
        'background_color' => '#ffffff',
        'border_color' => '#cccccc',
        'border_width' => 1,
        'border_radius' => 5,
        'text_color' => '#333333',
        'font_family' => 'Arial, sans-serif',
        'font_size' => 14,
        'padding' => 10,
        'margin' => 10,
        'image_width' => 80,
        'image_height' => 80,
        'image_border_radius' => 5,
      ],
    ];

    $settings = array_merge($default_settings, $settings);

    $form['#attributes']['class'][] = 'organization-chart-theme-form';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Theme Name'),
      '#description' => $this->t('Enter a name for this theme.'),
      '#default_value' => $theme ? $theme->name : '',
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    // General settings.
    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General Settings'),
      '#open' => TRUE,
    ];

    $form['general']['horizontal_scroll'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable horizontal scrolling'),
      '#description' => $this->t('Allow horizontal scrolling for wide charts.'),
      '#default_value' => $settings['general']['horizontal_scroll'],
    ];

    $form['general']['responsive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Responsive design'),
      '#description' => $this->t('Make the chart responsive to different screen sizes.'),
      '#default_value' => $settings['general']['responsive'],
    ];

    $form['general']['zoom_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable zoom functionality'),
      '#description' => $this->t('Allow users to zoom in and out of the chart.'),
      '#default_value' => $settings['general']['zoom_enabled'],
    ];

    $form['general']['fullscreen_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable fullscreen mode'),
      '#description' => $this->t('Allow users to view the chart in fullscreen.'),
      '#default_value' => $settings['general']['fullscreen_enabled'],
    ];

    $form['general']['popup_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable popup functionality'),
      '#description' => $this->t('Show element details in popups when clicked.'),
      '#default_value' => $settings['general']['popup_enabled'],
    ];

    $form['general']['animation_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable animations'),
      '#description' => $this->t('Add smooth animations when elements appear.'),
      '#default_value' => $settings['general']['animation_enabled'] ?? TRUE,
    ];

    $form['general']['animation_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Animation duration'),
      '#description' => $this->t('Duration of animations in milliseconds.'),
      '#default_value' => $settings['general']['animation_duration'] ?? 300,
      '#min' => 100,
      '#max' => 2000,
      '#field_suffix' => 'ms',
      '#states' => [
        'visible' => [
          ':input[name="animation_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Line style settings.
    $form['line_style'] = [
      '#type' => 'details',
      '#title' => $this->t('Line Style'),
      '#open' => TRUE,
    ];

    $form['line_style']['line_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Line color'),
      '#default_value' => $settings['line_style']['line_color'],
    ];

    $form['line_style']['line_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Line width'),
      '#default_value' => $settings['line_style']['line_width'],
      '#min' => 1,
      '#max' => 10,
      '#field_suffix' => 'px',
    ];

    $form['line_style']['line_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Line style'),
      '#default_value' => $settings['line_style']['line_style'],
      '#options' => [
        'solid' => $this->t('Solid'),
        'dashed' => $this->t('Dashed'),
        'dotted' => $this->t('Dotted'),
      ],
    ];

    $form['line_style']['line_opacity'] = [
      '#type' => 'range',
      '#title' => $this->t('Line opacity'),
      '#default_value' => $settings['line_style']['line_opacity'] ?? 100,
      '#min' => 0,
      '#max' => 100,
      '#step' => 5,
      '#field_suffix' => '%',
    ];

    // Item style settings.
    $form['item_style'] = [
      '#type' => 'details',
      '#title' => $this->t('Item Style'),
      '#open' => TRUE,
    ];

    $form['item_style']['background_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Background color'),
      '#default_value' => $settings['item_style']['background_color'],
    ];

    $form['item_style']['background_gradient'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable gradient background'),
      '#default_value' => $settings['item_style']['background_gradient'] ?? FALSE,
    ];

    $form['item_style']['background_color_secondary'] = [
      '#type' => 'color',
      '#title' => $this->t('Secondary background color (for gradient)'),
      '#default_value' => $settings['item_style']['background_color_secondary'] ?? '#f0f0f0',
      '#states' => [
        'visible' => [
          ':input[name="background_gradient"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['item_style']['border_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Border color'),
      '#default_value' => $settings['item_style']['border_color'],
    ];

    $form['item_style']['border_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Border width'),
      '#default_value' => $settings['item_style']['border_width'],
      '#min' => 0,
      '#max' => 10,
      '#field_suffix' => 'px',
    ];

    $form['item_style']['border_radius'] = [
      '#type' => 'number',
      '#title' => $this->t('Border radius'),
      '#default_value' => $settings['item_style']['border_radius'],
      '#min' => 0,
      '#max' => 50,
      '#field_suffix' => 'px',
    ];

    $form['item_style']['shadow_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable drop shadow'),
      '#default_value' => $settings['item_style']['shadow_enabled'] ?? TRUE,
    ];

    $form['item_style']['shadow_blur'] = [
      '#type' => 'number',
      '#title' => $this->t('Shadow blur'),
      '#default_value' => $settings['item_style']['shadow_blur'] ?? 8,
      '#min' => 0,
      '#max' => 20,
      '#field_suffix' => 'px',
      '#states' => [
        'visible' => [
          ':input[name="shadow_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['item_style']['text_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Text color'),
      '#default_value' => $settings['item_style']['text_color'],
    ];

    $form['item_style']['font_family'] = [
      '#type' => 'select',
      '#title' => $this->t('Font family'),
      '#default_value' => $settings['item_style']['font_family'],
      '#options' => [
        'Arial, sans-serif' => 'Arial',
        'Helvetica, sans-serif' => 'Helvetica',
        'Georgia, serif' => 'Georgia',
        'Times, serif' => 'Times',
        'Courier, monospace' => 'Courier',
        'Verdana, sans-serif' => 'Verdana',
        'Trebuchet MS, sans-serif' => 'Trebuchet MS',
        'Impact, sans-serif' => 'Impact',
      ],
      '#other_option' => TRUE,
    ];

    $form['item_style']['font_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Font size'),
      '#default_value' => $settings['item_style']['font_size'],
      '#min' => 8,
      '#max' => 48,
      '#field_suffix' => 'px',
    ];

    $form['item_style']['font_weight'] = [
      '#type' => 'select',
      '#title' => $this->t('Font weight'),
      '#default_value' => $settings['item_style']['font_weight'] ?? 'normal',
      '#options' => [
        'normal' => $this->t('Normal'),
        'bold' => $this->t('Bold'),
        '300' => $this->t('Light'),
        '600' => $this->t('Semi-bold'),
        '800' => $this->t('Extra-bold'),
      ],
    ];

    $form['item_style']['text_align'] = [
      '#type' => 'select',
      '#title' => $this->t('Text alignment'),
      '#default_value' => $settings['item_style']['text_align'] ?? 'center',
      '#options' => [
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
    ];

    $form['item_style']['padding'] = [
      '#type' => 'number',
      '#title' => $this->t('Padding'),
      '#default_value' => $settings['item_style']['padding'],
      '#min' => 0,
      '#max' => 50,
      '#field_suffix' => 'px',
    ];

    $form['item_style']['margin'] = [
      '#type' => 'number',
      '#title' => $this->t('Margin'),
      '#default_value' => $settings['item_style']['margin'],
      '#min' => 0,
      '#max' => 50,
      '#field_suffix' => 'px',
    ];

    $form['item_style']['min_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum width'),
      '#default_value' => $settings['item_style']['min_width'] ?? 150,
      '#min' => 100,
      '#max' => 500,
      '#field_suffix' => 'px',
    ];

    $form['item_style']['max_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum width'),
      '#default_value' => $settings['item_style']['max_width'] ?? 250,
      '#min' => 150,
      '#max' => 600,
      '#field_suffix' => 'px',
    ];

    // Image style settings.
    $form['image_style'] = [
      '#type' => 'details',
      '#title' => $this->t('Image Style'),
      '#open' => TRUE,
    ];

    $form['image_style']['image_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Image width'),
      '#default_value' => $settings['item_style']['image_width'],
      '#min' => 20,
      '#max' => 200,
      '#field_suffix' => 'px',
    ];

    $form['image_style']['image_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Image height'),
      '#default_value' => $settings['item_style']['image_height'],
      '#min' => 20,
      '#max' => 200,
      '#field_suffix' => 'px',
    ];

    $form['image_style']['image_border_radius'] = [
      '#type' => 'number',
      '#title' => $this->t('Image border radius'),
      '#default_value' => $settings['item_style']['image_border_radius'],
      '#min' => 0,
      '#max' => 50,
      '#field_suffix' => 'px',
    ];

    $form['image_style']['image_border_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Image border width'),
      '#default_value' => $settings['item_style']['image_border_width'] ?? 2,
      '#min' => 0,
      '#max' => 10,
      '#field_suffix' => 'px',
    ];

    $form['image_style']['image_border_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Image border color'),
      '#default_value' => $settings['item_style']['image_border_color'] ?? '#ffffff',
    ];

    $form['theme_id'] = [
      '#type' => 'value',
      '#value' => $theme_id,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $theme_id ? $this->t('Update Theme') : $this->t('Create Theme'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('organization_chart.themes_list'),
      '#attributes' => ['class' => ['button']],
    ];

    if ($theme_id) {
      $form['actions']['duplicate'] = [
        '#type' => 'submit',
        '#value' => $this->t('Duplicate Theme'),
        '#submit' => ['::duplicateTheme'],
        '#attributes' => ['class' => ['button']],
      ];
    }

    // Attach library for theme preview.
    $form['#attached']['library'][] = 'organization_chart/admin';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $theme_id = $form_state->getValue('theme_id');

    // Check for duplicate names.
    $query = $this->database->select('organization_chart_themes', 'oct')
      ->fields('oct', ['id'])
      ->condition('name', $name);

    if ($theme_id) {
      $query->condition('id', $theme_id, '!=');
    }

    $existing = $query->execute()->fetchField();

    if ($existing) {
      $form_state->setErrorByName('name', $this->t('A theme with this name already exists.'));
    }

    // Validate dimensions.
    $min_width = $form_state->getValue('min_width');
    $max_width = $form_state->getValue('max_width');

    if ($min_width && $max_width && $min_width >= $max_width) {
      $form_state->setErrorByName('max_width', $this->t('Maximum width must be greater than minimum width.'));
    }

    $image_width = $form_state->getValue('image_width');
    $image_height = $form_state->getValue('image_height');

    if ($image_width && $image_height && abs($image_width - $image_height) > 50) {
      $form_state->setWarningByName('image_height', $this->t('Image dimensions are very different. This may cause distortion.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $theme_id = $form_state->getValue('theme_id');
    $time = \Drupal::time()->getRequestTime();

    // Collect settings.
    $settings = [
      'general' => [
        'horizontal_scroll' => (bool) $form_state->getValue('horizontal_scroll'),
        'responsive' => (bool) $form_state->getValue('responsive'),
        'zoom_enabled' => (bool) $form_state->getValue('zoom_enabled'),
        'fullscreen_enabled' => (bool) $form_state->getValue('fullscreen_enabled'),
        'popup_enabled' => (bool) $form_state->getValue('popup_enabled'),
        'animation_enabled' => (bool) $form_state->getValue('animation_enabled'),
        'animation_duration' => (int) $form_state->getValue('animation_duration'),
      ],
      'line_style' => [
        'line_color' => $form_state->getValue('line_color'),
        'line_width' => (int) $form_state->getValue('line_width'),
        'line_style' => $form_state->getValue('line_style'),
        'line_opacity' => (int) $form_state->getValue('line_opacity'),
      ],
      'item_style' => [
        'background_color' => $form_state->getValue('background_color'),
        'background_gradient' => (bool) $form_state->getValue('background_gradient'),
        'background_color_secondary' => $form_state->getValue('background_color_secondary'),
        'border_color' => $form_state->getValue('border_color'),
        'border_width' => (int) $form_state->getValue('border_width'),
        'border_radius' => (int) $form_state->getValue('border_radius'),
        'shadow_enabled' => (bool) $form_state->getValue('shadow_enabled'),
        'shadow_blur' => (int) $form_state->getValue('shadow_blur'),
        'text_color' => $form_state->getValue('text_color'),
        'font_family' => $form_state->getValue('font_family'),
        'font_size' => (int) $form_state->getValue('font_size'),
        'font_weight' => $form_state->getValue('font_weight'),
        'text_align' => $form_state->getValue('text_align'),
        'padding' => (int) $form_state->getValue('padding'),
        'margin' => (int) $form_state->getValue('margin'),
        'min_width' => (int) $form_state->getValue('min_width'),
        'max_width' => (int) $form_state->getValue('max_width'),
        'image_width' => (int) $form_state->getValue('image_width'),
        'image_height' => (int) $form_state->getValue('image_height'),
        'image_border_radius' => (int) $form_state->getValue('image_border_radius'),
        'image_border_width' => (int) $form_state->getValue('image_border_width'),
        'image_border_color' => $form_state->getValue('image_border_color'),
      ],
    ];

    try {
      if ($theme_id) {
        // Update existing theme.
        $this->database->update('organization_chart_themes')
          ->fields([
            'name' => $name,
            'settings' => json_encode($settings),
            'updated' => $time,
          ])
          ->condition('id', $theme_id)
          ->execute();

        // Clear caches for all charts using this theme.
        \Drupal::service('cache_tags.invalidator')->invalidateTags([
          'organization_chart_theme:' . $theme_id
        ]);

        $this->messenger()->addMessage($this->t('Theme "@name" has been updated.', ['@name' => $name]));
      }
      else {
        // Create new theme.
        $this->database->insert('organization_chart_themes')
          ->fields([
            'name' => $name,
            'settings' => json_encode($settings),
            'created' => $time,
            'updated' => $time,
          ])
          ->execute();

        $this->messenger()->addMessage($this->t('Theme "@name" has been created.', ['@name' => $name]));
      }

      $form_state->setRedirect('organization_chart.themes_list');
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while saving the theme: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Duplicate theme submit handler.
   */
  public function duplicateTheme(array &$form, FormStateInterface $form_state) {
    $theme_id = $form_state->getValue('theme_id');
    $name = $form_state->getValue('name') . ' (Copy)';

    try {
      // Get original theme.
      $original_theme = $this->database->select('organization_chart_themes', 'oct')
        ->fields('oct')
        ->condition('id', $theme_id)
        ->execute()
        ->fetchObject();

      if ($original_theme) {
        $time = \Drupal::time()->getRequestTime();

        $new_theme_id = $this->database->insert('organization_chart_themes')
          ->fields([
            'name' => $name,
            'settings' => $original_theme->settings,
            'created' => $time,
            'updated' => $time,
          ])
          ->execute();

        $this->messenger()->addMessage($this->t('Theme has been duplicated successfully.'));
        $form_state->setRedirect('organization_chart.theme_edit', ['theme_id' => $new_theme_id]);
      }
      else {
        $this->messenger()->addError($this->t('Original theme not found.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Failed to duplicate theme: @error', ['@error' => $e->getMessage()]));
    }
  }
}
