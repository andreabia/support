<?php

/**
 * @file
 * Contains \Drupal\support_ticket\Form\SupportTicketPreviewForm.
 */

namespace Drupal\support_ticket\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains a form for switching the view mode of a support ticket during preview.
 */
class SupportTicketPreviewForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity.manager'), $container->get('config.factory'));
  }

  /**
   * Constructs a new SupportTicketPreviewForm.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityManagerInterface $entity_manager, ConfigFactoryInterface $config_factory) {
    $this->entityManager = $entity_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'support_ticket_preview_form_select';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\EntityInterface $support_ticket
   *   The support ticket being previewed
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $support_ticket = NULL) {
    $view_mode = $support_ticket->preview_view_mode;

    $query_options = $support_ticket->isNew() ? array('query' => array('uuid' => $support_ticket->uuid())) : array();
    $form['backlink'] = array(
      '#type' => 'link',
      '#title' => $this->t('Back to ticket editing'),
      '#url' => $support_ticket->isNew() ? Url::fromRoute('support_ticket.add', ['support_ticket_type' => $support_ticket->bundle()]) : $support_ticket->urlInfo('edit-form'),
      '#options' => array('attributes' => array('class' => array('support-ticket-preview-backlink'))) + $query_options,
    );

    $view_mode_options = $this->getViewModeOptions($support_ticket);

    $form['uuid'] = array(
      '#type' => 'value',
      '#value' => $support_ticket->uuid(),
    );

    $form['view_mode'] = array(
      '#type' => 'select',
      '#title' => $this->t('View mode'),
      '#options' => $view_mode_options,
      '#default_value' => $view_mode,
      '#attributes' => array(
        'data-drupal-autosubmit' => TRUE,
      )
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Switch'),
      '#attributes' => array(
        'class' => array('js-hide'),
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.support_ticket.preview', array(
      'support_ticket_preview' => $form_state->getValue('uuid'),
      'view_mode_id' => $form_state->getValue('view_mode'),
    ));
  }

  /**
   * Gets the list of available view modes for the current support ticket.
   *
   * @param EntityInterface $support_ticket
   *   The support ticket being previewed.
   *
   * @return array
   *   List of available view modes for the current support ticket.
   */
  protected function getViewModeOptions(EntityInterface $support_ticket) {
    $load_ids = array();
    $view_mode_options = array();

    // Load all the support ticket's view modes.
    $view_modes = $this->entityManager->getViewModes('support_ticket');

    // Get the list of available view modes for the current support_ticket's bundle.
    $ids = $this->configFactory->listAll('core.entity_view_display.support_ticket.' . $support_ticket->bundle());
    foreach ($ids as $id) {
      $config_id = str_replace('core.entity_view_display' . '.', '', $id);
      $load_ids[] = $config_id;
    }
    $displays = entity_load_multiple('entity_view_display', $load_ids);

    // Generate the display options array.
    foreach ($displays as $display) {

      $view_mode_name = $display->get('mode');

      // Skip view modes that are not used in the front end.
      if (in_array($view_mode_name, array('rss', 'search_index'))) {
        continue;
      }

      if ($display->status()) {
        $view_mode_options[$view_mode_name] = ($view_mode_name == 'default') ? t('Default') : $view_modes[$view_mode_name]['label'];
      }
    }

    return $view_mode_options;
  }

}
