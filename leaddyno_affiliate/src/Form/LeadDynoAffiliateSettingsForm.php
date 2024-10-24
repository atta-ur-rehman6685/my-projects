<?php
namespace Drupal\leaddyno_affiliate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class LeadDynoAffiliateSettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'leaddyno_affiliate_settings_form';
  }

  protected function getEditableConfigNames() {
    return ['leaddyno_affiliate.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('leaddyno_affiliate.settings');

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('LeadDyno API Key'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('leaddyno_affiliate.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
