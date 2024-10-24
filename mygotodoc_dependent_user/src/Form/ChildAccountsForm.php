<?php

namespace Drupal\mygotodoc_dependent_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\commerce_order\Entity\Order;

/**
 * Provides a form to list nodes titled from 'child_accounts' content type.
 */
class ChildAccountsForm extends FormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'child_accounts_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $current_user = \Drupal::currentUser();
        $current_user_id = $current_user->id();
        $current_user_name = $current_user->getDisplayName();

        // // Load the current user's order to check the 'field_order_by' value.
        // $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
        // $orders = $order_storage->loadByProperties([
        //     'uid' => $current_user_id,
        //     'type' => 'default',
        //     'cart' => TRUE,
        // ]);

        $session = \Drupal::service('session');
        $current_order = $session->get('order_id_for_title');

        $default_value = '_self'; // Default to parent user.

        // Check if the order ID exists in the session and load the order if it does.
        if (!empty($current_order)) {
            // Load the order entity using the order ID.
            $order = Order::load($current_order);

            if (!empty($order)) {
                $order_by = $order->get('field_order_by')->target_id;
                if (!empty($order_by)) {
                    $default_value = $order_by; // Set default value to the current order's 'field_order_by' value.
                }
            }
        }
        

        // Updated query with access check setting.
        $query = \Drupal::entityQuery('node')
            ->condition('type', 'child_accounts')
            ->condition('field_parent_user_id', $current_user_id)
            ->condition('status', 1)  // Ensure the node is published.
            ->accessCheck(FALSE);  // Ensures that access checks are explicitly set.

        $nids = $query->execute();

        $options = [];
        // $options['_self'] = $this->t('Parent (@name)', ['@name' => $current_user_name]); // Add the parent user at the top.
        $options['_self'] = 'self ('. $current_user_name .')'; // Add the parent user at the top.

       if (!empty($nids)) {
           foreach ($nids as $nid) {
               $node = Node::load($nid);
               $options[$node->id()] = $node->getTitle();
           }
       }

        // Create the select list of node titles with the current value selected by default.
        $form['node_titles'] = [
            '#type' => 'select',
            '#title' => $this->t('Who will be placing the order?'),
            '#options' => $options,
            '#default_value' => $default_value, // Set the default value based on the current order's 'field_order_by'
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
        ];
        
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $selected_value = $form_state->getValue('node_titles');
        
        $session = \Drupal::service('session');
        $current_order = $session->get('order_id_for_title');
        // Load the order entity using the order ID.
        $order = Order::load($current_order);

        // Check if the order and node entities exist.
        if ($order && $selected_value) {    
            // Check if the selected value is the parent user ID or a child account node ID.
            if ($selected_value === '_self') {
                // If the parent user is selected, clear the field_order_by field.
                $order->set('field_order_by', NULL);
                $order->save();

                // \Drupal::messenger()->addMessage($this->t('Order updated: no child account selected.'));
            } else {
                 // Set the 'field_order_by' entity reference field with the node entity.
                $order->set('field_order_by', ['target_id' => $selected_value]);

                // Save the order entity to store the changes.
                $order->save();
            }
        }
        // Redirect the user to the checkout page, passing the order ID as a parameter.
        $form_state->setRedirect('commerce_checkout.form', ['commerce_order' => $order->id()]);
    }
}
