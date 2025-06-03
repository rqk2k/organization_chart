<?php

namespace Drupal\organization_chart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AJAX controller for organization chart operations.
 */
class OrganizationChartAjaxController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new OrganizationChartAjaxController object.
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
   * Handles AJAX operations for chart elements.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function elementOperation(Request $request) {
    $operation = $request->request->get('operation');
    $response_data = ['success' => FALSE];

    switch ($operation) {
      case 'add_element':
        $response_data = $this->addElement($request);
        break;

      case 'update_element':
        $response_data = $this->updateElement($request);
        break;

      case 'delete_element':
        $response_data = $this->deleteElement($request);
        break;

      case 'move_element':
        $response_data = $this->moveElement($request);
        break;

      case 'get_element':
        $response_data = $this->getElement($request);
        break;

      case 'save_chart':
        $response_data = $this->saveChart($request);
        break;

      case 'duplicate_element':
        $response_data = $this->duplicateElement($request);
        break;

      case 'bulk_operation':
        $response_data = $this->bulkOperation($request);
        break;

      case 'upload_image':
        $response_data = $this->uploadImage($request);
        break;

      case 'quick_edit':
        $response_data = $this->quickEdit($request);
        break;

      case 'get_shortcode_data':
        $response_data = $this->getShortcodeData($request);
        break;

      default:
        $response_data['message'] = 'Unknown operation';
    }

    return new JsonResponse($response_data);
  }

  /**
   * Adds a new element to the chart.
   */
  private function addElement(Request $request) {
    $chart_id = $request->request->get('chart_id');
    $parent_id = $request->request->get('parent_id');
    $position_x = $request->request->get('position_x', 0);
    $position_y = $request->request->get('position_y', 0);

    try {
      $element_id = $this->database->insert('organization_chart_elements')
        ->fields([
          'chart_id' => $chart_id,
          'parent_id' => $parent_id,
          'title' => 'New Element',
          'description' => '',
          'image_url' => '',
          'link_url' => '',
          'position_x' => $position_x,
          'position_y' => $position_y,
          'theme_id' => NULL,
          'weight' => 0,
        ])
        ->execute();

      return [
        'success' => TRUE,
        'element_id' => $element_id,
        'message' => 'Element added successfully',
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to add element: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Updates an existing element.
   */
  private function updateElement(Request $request) {
    $element_id = $request->request->get('element_id');
    $data = $request->request->all();

    try {
      $fields = [];

      // Filter allowed fields.
      $allowed_fields = [
        'title', 'description', 'image_url', 'link_url',
        'position_x', 'position_y', 'theme_id', 'weight', 'parent_id'
      ];

      foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
          $fields[$field] = $data[$field];
        }
      }

      if (!empty($fields)) {
        $this->database->update('organization_chart_elements')
          ->fields($fields)
          ->condition('id', $element_id)
          ->execute();
      }

      return [
        'success' => TRUE,
        'message' => 'Element updated successfully',
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to update element: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Deletes an element and its children.
   */
  private function deleteElement(Request $request) {
    $element_id = $request->request->get('element_id');

    try {
      // Get all child elements recursively.
      $child_ids = $this->getChildElementIds($element_id);
      $all_ids = array_merge([$element_id], $child_ids);

      // Delete all elements.
      $this->database->delete('organization_chart_elements')
        ->condition('id', $all_ids, 'IN')
        ->execute();

      return [
        'success' => TRUE,
        'deleted_ids' => $all_ids,
        'message' => 'Element and children deleted successfully',
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to delete element: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Moves an element to a new position.
   */
  private function moveElement(Request $request) {
    $element_id = $request->request->get('element_id');
    $position_x = $request->request->get('position_x');
    $position_y = $request->request->get('position_y');
    $parent_id = $request->request->get('parent_id');

    try {
      $fields = [
        'position_x' => $position_x,
        'position_y' => $position_y,
      ];

      if ($parent_id !== NULL) {
        $fields['parent_id'] = $parent_id;
      }

      $this->database->update('organization_chart_elements')
        ->fields($fields)
        ->condition('id', $element_id)
        ->execute();

      return [
        'success' => TRUE,
        'message' => 'Element moved successfully',
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to move element: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Gets element data.
   */
  private function getElement(Request $request) {
    $element_id = $request->request->get('element_id');

    try {
      $element = $this->database->select('organization_chart_elements', 'oce')
        ->fields('oce')
        ->condition('id', $element_id)
        ->execute()
        ->fetchObject();

      if ($element) {
        return [
          'success' => TRUE,
          'element' => (array) $element,
        ];
      }
      else {
        return [
          'success' => FALSE,
          'message' => 'Element not found',
        ];
      }
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to get element: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Saves the entire chart with all elements.
   */
  private function saveChart(Request $request) {
    $chart_id = $request->request->get('chart_id');
    $elements_json = $request->request->get('elements');

    try {
      $elements = json_decode($elements_json, TRUE);
      if (!$elements) {
        return [
          'success' => FALSE,
          'message' => 'Invalid elements data',
        ];
      }

      // Start transaction.
      $transaction = $this->database->startTransaction();

      try {
        // Delete existing elements for this chart.
        $this->database->delete('organization_chart_elements')
          ->condition('chart_id', $chart_id)
          ->execute();

        // Insert updated elements.
        foreach ($elements as $element) {
          // Skip temporary IDs that aren't saved yet.
          if (strpos($element['id'], 'temp_') === 0) {
            // Generate new ID for temporary elements.
            $element_id = $this->database->insert('organization_chart_elements')
              ->fields([
                'chart_id' => $chart_id,
                'parent_id' => $element['parent_id'],
                'title' => $element['title'] ?? '',
                'description' => $element['description'] ?? '',
                'image_url' => $element['image_url'] ?? '',
                'link_url' => $element['link_url'] ?? '',
                'position_x' => $element['position_x'] ?? 0,
                'position_y' => $element['position_y'] ?? 0,
                'theme_id' => $element['theme_id'],
                'weight' => $element['weight'] ?? 0,
              ])
              ->execute();
          }
          else {
            // Update existing element.
            $this->database->insert('organization_chart_elements')
              ->fields([
                'id' => $element['id'],
                'chart_id' => $chart_id,
                'parent_id' => $element['parent_id'],
                'title' => $element['title'] ?? '',
                'description' => $element['description'] ?? '',
                'image_url' => $element['image_url'] ?? '',
                'link_url' => $element['link_url'] ?? '',
                'position_x' => $element['position_x'] ?? 0,
                'position_y' => $element['position_y'] ?? 0,
                'theme_id' => $element['theme_id'],
                'weight' => $element['weight'] ?? 0,
              ])
              ->execute();
          }
        }

        // Update chart timestamp.
        $this->database->update('organization_charts')
          ->fields(['updated' => \Drupal::time()->getRequestTime()])
          ->condition('id', $chart_id)
          ->execute();

        // Clear caches.
        \Drupal::service('cache_tags.invalidator')->invalidateTags([
          'organization_chart:' . $chart_id
        ]);

        return [
          'success' => TRUE,
          'message' => 'Chart saved successfully',
        ];
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        throw $e;
      }
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to save chart: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Duplicates an element.
   */
  private function duplicateElement(Request $request) {
    $element_id = $request->request->get('element_id');
    $offset_x = $request->request->get('offset_x', 50);
    $offset_y = $request->request->get('offset_y', 50);

    try {
      // Get original element.
      $original = $this->database->select('organization_chart_elements', 'oce')
        ->fields('oce')
        ->condition('id', $element_id)
        ->execute()
        ->fetchObject();

      if (!$original) {
        return [
          'success' => FALSE,
          'message' => 'Element not found',
        ];
      }

      // Create duplicate.
      $new_element_id = $this->database->insert('organization_chart_elements')
        ->fields([
          'chart_id' => $original->chart_id,
          'parent_id' => $original->parent_id,
          'title' => $original->title . ' (Copy)',
          'description' => $original->description,
          'image_url' => $original->image_url,
          'link_url' => $original->link_url,
          'position_x' => $original->position_x + $offset_x,
          'position_y' => $original->position_y + $offset_y,
          'theme_id' => $original->theme_id,
          'weight' => $original->weight,
        ])
        ->execute();

      return [
        'success' => TRUE,
        'element_id' => $new_element_id,
        'message' => 'Element duplicated successfully',
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to duplicate element: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Handles bulk operations on multiple charts.
   */
  private function bulkOperation(Request $request) {
    $action = $request->request->get('action');
    $ids = $request->request->get('ids', []);

    if (empty($ids) || !is_array($ids)) {
      return [
        'success' => FALSE,
        'message' => 'No valid IDs provided',
      ];
    }

    try {
      switch ($action) {
        case 'delete':
          return $this->bulkDelete($ids);

        case 'duplicate':
          return $this->bulkDuplicate($ids);

        default:
          return [
            'success' => FALSE,
            'message' => 'Unknown bulk action',
          ];
      }
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Bulk operation failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Bulk delete charts.
   */
  private function bulkDelete($chart_ids) {
    $transaction = $this->database->startTransaction();

    try {
      // Delete elements first.
      $this->database->delete('organization_chart_elements')
        ->condition('chart_id', $chart_ids, 'IN')
        ->execute();

      // Delete charts.
      $deleted_count = $this->database->delete('organization_charts')
        ->condition('id', $chart_ids, 'IN')
        ->execute();

      // Clear caches.
      $cache_tags = [];
      foreach ($chart_ids as $chart_id) {
        $cache_tags[] = 'organization_chart:' . $chart_id;
      }
      \Drupal::service('cache_tags.invalidator')->invalidateTags($cache_tags);

      return [
        'success' => TRUE,
        'message' => "Successfully deleted {$deleted_count} chart(s)",
      ];
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Bulk duplicate charts.
   */
  private function bulkDuplicate($chart_ids) {
    $service = \Drupal::service('organization_chart.service');
    $duplicated_count = 0;

    foreach ($chart_ids as $chart_id) {
      // Get original chart name.
      $original_name = $this->database->select('organization_charts', 'oc')
        ->fields('oc', ['name'])
        ->condition('id', $chart_id)
        ->execute()
        ->fetchField();

      if ($original_name) {
        $new_name = $original_name . ' (Copy)';
        $new_chart_id = $service->duplicateChart($chart_id, $new_name);

        if ($new_chart_id) {
          $duplicated_count++;
        }
      }
    }

    return [
      'success' => TRUE,
      'message' => "Successfully duplicated {$duplicated_count} chart(s)",
    ];
  }

  /**
   * Handles image upload.
   */
  private function uploadImage(Request $request) {
    $uploaded_files = $request->files->get('files', []);

    if (empty($uploaded_files['image'])) {
      return [
        'success' => FALSE,
        'message' => 'No image file uploaded',
      ];
    }

    $file = $uploaded_files['image'];

    try {
      // Validate file type.
      $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!in_array($file->getMimeType(), $allowed_types)) {
        return [
          'success' => FALSE,
          'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.',
        ];
      }

      // Validate file size (2MB max).
      if ($file->getSize() > 2097152) {
        return [
          'success' => FALSE,
          'message' => 'File too large. Maximum size is 2MB.',
        ];
      }

      // Create upload directory if it doesn't exist.
      $upload_dir = 'public://organization_chart/';
      \Drupal::service('file_system')->prepareDirectory($upload_dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);

      // Generate unique filename.
      $filename = uniqid() . '_' . $file->getClientOriginalName();
      $destination = $upload_dir . $filename;

      // Move uploaded file.
      $file->move(\Drupal::service('file_system')->realpath($upload_dir), $filename);

      // Create file entity.
      $file_entity = \Drupal\file\Entity\File::create([
        'langcode' => 'en',
        'uid' => \Drupal::currentUser()->id(),
        'status' => 1,
        'filename' => $filename,
        'uri' => $destination,
        'filesize' => $file->getSize(),
        'filemime' => $file->getMimeType(),
      ]);
      $file_entity->save();

      // Generate URL.
      $url = \Drupal::service('file_url_generator')->generateAbsoluteString($destination);

      return [
        'success' => TRUE,
        'file_id' => $file_entity->id(),
        'url' => $url,
        'message' => 'Image uploaded successfully',
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Upload failed: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Handles quick edit operations.
   */
  private function quickEdit(Request $request) {
    $id = $request->request->get('id');
    $field = $request->request->get('field');
    $value = $request->request->get('value');

    if (!$id || !$field || !$value) {
      return [
        'success' => FALSE,
        'message' => 'Missing required parameters',
      ];
    }

    try {
      // Only allow editing the name field for security.
      if ($field === 'name') {
        $this->database->update('organization_charts')
          ->fields(['name' => $value])
          ->condition('id', $id)
          ->execute();

        return [
          'success' => TRUE,
          'message' => 'Chart name updated successfully',
        ];
      }
      else {
        return [
          'success' => FALSE,
          'message' => 'Field not editable',
        ];
      }
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to update: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Gets data for shortcode generator.
   */
  private function getShortcodeData(Request $request) {
    try {
      // Get charts.
      $charts = $this->database->select('organization_charts', 'oc')
        ->fields('oc', ['id', 'name'])
        ->orderBy('name')
        ->execute()
        ->fetchAll();

      // Get themes.
      $themes = $this->database->select('organization_chart_themes', 'oct')
        ->fields('oct', ['id', 'name'])
        ->orderBy('name')
        ->execute()
        ->fetchAll();

      return [
        'success' => TRUE,
        'charts' => array_map(function($chart) {
          return ['id' => $chart->id, 'name' => $chart->name];
        }, $charts),
        'themes' => array_map(function($theme) {
          return ['id' => $theme->id, 'name' => $theme->name];
        }, $themes),
      ];
    }
    catch (\Exception $e) {
      return [
        'success' => FALSE,
        'message' => 'Failed to load data: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Gets all child element IDs recursively.
   *
   * @param int $parent_id
   *   The parent element ID.
   *
   * @return array
   *   Array of child element IDs.
   */
  private function getChildElementIds($parent_id) {
    $child_ids = [];

    $children = $this->database->select('organization_chart_elements', 'oce')
      ->fields('oce', ['id'])
      ->condition('parent_id', $parent_id)
      ->execute()
      ->fetchCol();

    foreach ($children as $child_id) {
      $child_ids[] = $child_id;
      // Recursively get grandchildren.
      $child_ids = array_merge($child_ids, $this->getChildElementIds($child_id));
    }

    return $child_ids;
  }
}
