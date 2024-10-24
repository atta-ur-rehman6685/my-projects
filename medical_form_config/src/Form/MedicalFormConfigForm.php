<?php

namespace Drupal\medical_form_config\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\conditional_fields\Conditions;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Configuration form for medical form fields.
 */
class MedicalFormConfigForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The conditions service.
   *
   * @var \Drupal\conditional_fields\Conditions
   */
  protected $conditions;

  /**
   * Constructs a new MedicalFormConfigForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\conditional_fields\Conditions $conditions
   *   The conditions service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Conditions $conditions) {
    $this->entityTypeManager = $entity_type_manager;
    $this->conditions = $conditions;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('conditional_fields.conditions')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['medical_form_config.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'medical_form_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('medical_form_config.settings');
    $field_product_mapping = $config->get('field_product_mapping');

    // Get all fields of the medical_form content type.
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'medical_form');
    // kint($field_definitions);
    // exit;
    // Get all products of type essential_medkits.
    $product_storage = $this->entityTypeManager->getStorage('commerce_product');
    $products = $product_storage->loadByProperties(['type' => 'essential_medkits']);
    $product_options = [];
    foreach ($products as $product) {
      $product_options[$product->id()] = $product->label();
    }

    // Get dependent fields.
    $dependent_fields = $this->getDependentFields('paragraph', 'medical_form');

    // Build the form with field titles and multi-select lists for products.
    foreach ($field_definitions as $field_name => $field_definition) {
      if (!$field_definition->getFieldStorageDefinition()->isBaseField() && !in_array($field_name, $dependent_fields)) {
        $field_label = $field_definition->getLabel();
        $form[$field_name . '_label'] = [
          '#type' => 'item',
          '#markup' => '<h6>' . $field_label . '</h6>',
        ];
        $form[$field_name] = [
          '#type' => 'select',
          // '#title' => $this->t('Select Products for ' . $field_label),
          '#title' => $this->t('Select Products:'),
          '#options' => $product_options,
          '#multiple' => TRUE,
          '#default_value' => isset($field_product_mapping[$field_name]) ? $field_product_mapping[$field_name] : [],
        ];
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Gets the list of dependent fields for the specified entity type and bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle name.
   *
   * @return array
   *   An array of dependent field names.
   */
  protected function getDependentFields($entity_type, $bundle) {
    $form_display_entity = $this->entityTypeManager
      ->getStorage('entity_form_display')
      ->load("$entity_type.$bundle.default");

    $dependent_fields = [];

    if ($form_display_entity) {
      $fields = $form_display_entity->getComponents();

      foreach ($fields as $field_name => $field) {
        if (!empty($field['third_party_settings']['conditional_fields'])) {
          $dependent_fields[] = $field_name;
        }
      }
    }
    // kint($dependent_fields);
    // exit;
    return $dependent_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $field_product_mapping = [];
    // kint($form_state);

    // Get all fields of the medical_form content type to validate field names
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('paragraph', 'medical_form');

    // Extract only the keys related to field products
    foreach ($field_definitions as $field_name => $field_definition) {
      if (!$field_definition->getFieldStorageDefinition()->isBaseField()) {
        // Get the selected products for each field.
        $selected_products = $form_state->getValue($field_name);
        if ($selected_products !== NULL && is_array($selected_products)) {
          $field_product_mapping[$field_name] = $selected_products;
        }
      }
    }
    // kint($field_product_mapping);
    // exit;

    $this->config('medical_form_config.settings')
      ->set('field_product_mapping', $field_product_mapping)
      ->save();
  }

}
