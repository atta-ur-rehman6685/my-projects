<?php

namespace Drupal\mygotodoc_independent_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\RedirectCommand;

/**
 * Provides a modal confirmation and user creation.
 */
class IndependentUserController extends ControllerBase
{

    /**
     * Displays the confirmation dialog for user creation.
     */
    public function confirmModal(NodeInterface $node)
    {
        return [
            '#type' => 'markup',
            '#markup' => '<p>' . $this->t('Are you sure you want to create an independent user for this dependent account?') . '</p>',
            'actions' => [
                '#type' => 'actions',
                'ok_button' => [
                    '#type' => 'link',
                    '#title' => $this->t('OK'),
                    '#url' => \Drupal\Core\Url::fromRoute('mygotodoc_independent_user.create_user', ['node' => $node->id()]),
                    '#attributes' => ['class' => ['use-ajax', 'btn', 'btn-dark']],
                    '#ajax' => [
                        'callback' => '::ajaxCreateUser',
                        'event' => 'click',
                    ],
                ],
                'cancel_button' => [
                    '#type' => 'link',
                    '#title' => $this->t('Cancel'),
                    '#url' => \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $node->id()]),
                    '#attributes' => ['class' => ['btn', 'btn-dark']],
                ],
            ],
            '#attached' => [
                'library' => ['core/drupal.dialog.ajax'],
            ],
        ];
    }


    /**
     * Ajax callback to create the user and update orders.
     */
    public function ajaxCreateUser(NodeInterface $node, $flag = NULL)
    {
        // Initialize an Ajax response.
        $response = new AjaxResponse();

        if ($node->bundle() == 'child_accounts') {
            $email = $node->get('field_email')->value;
            $name = $node->get('field_first_name')->value;

            // Check if the user already exists.
            if (!user_load_by_mail($email)) {
                // Create a new user with "patient" role.
                $user = User::create([
                    'name' => $name,
                    'mail' => $email,
                    'status' => 1, // edited by atta.
                    'roles' => ['patient'],
                ]);
                $user->save();

                // Copy paragraph field data from node to user account.
                if ($node->hasField('field_medical_form_inputs') && !$node->get('field_medical_form_inputs')->isEmpty()) {
                    $paragraph_items = $node->get('field_medical_form_inputs')->getValue();

                    // Iterate over each paragraph in the node's field_medical_form_inputs.
                    foreach ($paragraph_items as $paragraph_item) {
                        $paragraph_entity = \Drupal::entityTypeManager()->getStorage('paragraph')->load($paragraph_item['target_id']);
                        if ($paragraph_entity) {
                            // Clone the paragraph entity.
                            $cloned_paragraph = $paragraph_entity->createDuplicate();
                            $cloned_paragraph->save();

                            // Assign the cloned paragraph to the user's account field.
                            $user->get('field_medical_form_inputs')->appendItem([
                                'target_id' => $cloned_paragraph->id(),
                                'target_revision_id' => $cloned_paragraph->getRevisionId(),
                            ]);
                        }
                    }

                    // Save the updated user profile with the paragraph field data.
                    $user->save();
                }

                // Fetch all orders that might reference the node.
                $orders = $this->getOrdersByNodeReference($node);

                // Loop through the orders and update them.
                foreach ($orders as $order) {
                    // Check if the node is referenced in the field_order_by.
                    if ($order->get('field_order_by')->target_id == $node->id()) {
                        // Empty the field_order_by.
                        $order->set('field_order_by', NULL);

                        // Update the customer info to the new user.
                        $order->set('uid', $user->id());

                        // Update the contact email field directly in the order.
                        if ($order->hasField('mail')) {
                            $order->set('mail', $email);  // Update the order's contact email
                        }

                        // Check if the billing profile exists and has a field that stores the email.
                        $billing_profile = $order->getBillingProfile();
                        if ($billing_profile && $billing_profile->hasField('email')) {
                            $billing_profile->set('email', $email);  // Set email in profile if such field exists.
                            $billing_profile->save();
                        }

                        // Now check and update the medical form referenced in the field_medical_form_refrence.
                        if ($order->hasField('field_medical_form_refrence') && !$order->get('field_medical_form_refrence')->isEmpty()) {
                            $medical_form_id = $order->get('field_medical_form_refrence')->target_id;
                            $medical_form_node = \Drupal::entityTypeManager()->getStorage('node')->load($medical_form_id);

                            // If the node exists, update the author.
                            if ($medical_form_node) {
                                $medical_form_node->setOwnerId($user->id());
                                $medical_form_node->save();
                            }
                        }

                        // Save the updated order.
                        $order->save();
                    }
                }

                // Unpublish the node.
                $node->setUnpublished()->save();
                
                 //start added by atta
                 $login_link = user_pass_reset_url($user);

                 $config = \Drupal::config('system.site');
                 $sitelogin = \Drupal::request()->getSchemeAndHttpHost()."/user/login";
                 $emailFactory = \Drupal::service('email_factory');
                 $email = $emailFactory->newTypedEmail('mailer_mgtd_builder', 'newUserActivation');
                 $email->setVariable('user_name', $user->getAccountName());
                 $email->setVariable('site_name', $config->get('name'));
                 $email->setVariable('one_time_login_link', $login_link);
                 $email->setVariable('site_login', $sitelogin);
                 $email->setTo($user->getEmail());
                 $email->send();
                 //end added by atta
                if ($flag != 'independent_from_cron') {
                    // Close the modal and display a success message.
                    $response->addCommand(new CloseModalDialogCommand());
                    $response->addCommand(new RedirectCommand('/account-released-successfully'));
                }
            } else {
                if ($flag != 'independent_from_cron') {
                    // User already exists.
                    $response->addCommand(new RedirectCommand('/invalid-email'));
                }
            }
        }
        if ($flag != 'independent_from_cron') {
            return $response;
        }
    }

    /**
     * Fetches orders where the node is referenced in the field_order_by.
     */
    protected function getOrdersByNodeReference(Node $node)
    {
        // Load all orders that reference this node in 'field_order_by'.
        $orders = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadByProperties([
            'field_order_by' => $node->id(),
        ]);

        return $orders;
    }
}
