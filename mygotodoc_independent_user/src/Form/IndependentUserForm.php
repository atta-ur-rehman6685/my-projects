<?php

namespace Drupal\mygotodoc_independent_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\RedirectResponse;

class IndependentUserForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mygotodoc_independent_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    $node = Node::load($node);
    
    if ($node && $node->bundle() == 'child_accounts') {
      // Display the node title and email.
      $form['node_first_name'] = [
        '#markup' => '<p><strong>' . $this->t('Name:') . '</strong> ' . $node->get('field_first_name')->value . '</p>',
      ];
      $form['node_email'] = [
        '#markup' => '<p><strong>' . $this->t('Email:') . '</strong> ' . $node->get('field_email')->value . '</p>',
      ];

      // Add a submit button.
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create Independent User'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Load the node.
    $node = Node::load($form_state->getBuildInfo()['args'][0]);

    // Create a new user with "patient" role.
    if ($node) {
      $email = $node->get('field_email')->value;
      $name = $node->get('field_first_name')->value;

      $user = User::create([
        'name' => $name,
        'mail' => $email,
        'status' => 0, // Blocked by default.
        'roles' => ['patient'],
      ]);
      $user->save();

      // Unpublish the node.
      $node->setUnpublished()->save();

      \Drupal::messenger()->addMessage($this->t('New independent user created and the node has been unpublished.'));
    }

    // Redirect back to the node page.
    $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
  }
}
