<?php

namespace Drupal\organization_chart\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for deleting organization charts.
 */
class OrganizationChartDeleteForm extends ConfirmFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The chart to delete.
   *
   * @var object
   */
  protected $chart;

  /**
   * Constructs a new OrganizationChartDeleteForm object.
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
    return 'organization_chart_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $chart_id = NULL) {
    $this->chart = $this->database->select('organization_charts', 'oc')
      ->fields('oc')
      ->condition('id', $chart_id)
      ->execute()
      ->fetchObject();

    if (!$this->chart) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the organization chart "@name"?', [
      '@name' => $this->chart->name,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action cannot be undone. All elements in this chart will also be deleted.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('organization_chart.charts_list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      // Delete all elements first.
      $this->database->delete('organization_chart_elements')
        ->condition('chart_id', $this->chart->id)
        ->execute();

      // Delete the chart.
      $this->database->delete('organization_charts')
        ->condition('id', $this->chart->id)
        ->execute();

      // Clear caches.
      \Drupal::service('cache_tags.invalidator')->invalidateTags([
        'organization_chart:' . $this->chart->id
      ]);

      $this->messenger()->addMessage($this->t('Organization chart "@name" has been deleted.', [
        '@name' => $this->chart->name,
      ]));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while deleting the chart: @error', [
        '@error' => $e->getMessage(),
      ]));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}
