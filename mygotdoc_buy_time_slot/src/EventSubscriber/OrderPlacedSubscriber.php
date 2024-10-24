<?php

namespace Drupal\mygotdoc_buy_time_slot\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\leaddyno_affiliate\Service\LeadDynoPurchaseService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Serialization\Json;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class OrderPlacedSubscriber.
 */
class OrderPlacedSubscriber implements EventSubscriberInterface {

  protected $leadDynoPurchaseService;
  protected $logger;

  public function __construct(LeadDynoPurchaseService $leadDynoPurchaseService, LoggerChannelFactoryInterface $loggerFactory) {
    $this->leadDynoPurchaseService = $leadDynoPurchaseService;
    $this->logger = $loggerFactory->get('lead_dyno');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Use the string 'commerce_order.place.post_transition'.
      'commerce_order.place.post_transition' => 'onOrderPlaced',
    ];
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('leaddyno_affiliate.purchase_service'),
      $container->get('logger.factory')
    );
  }

  /**
   * Reacts to the order placed event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function onOrderPlaced(WorkflowTransitionEvent $event) {
    $entity = $event->getEntity();

    // Ensure that the entity is an order.
    if ($entity instanceof OrderInterface) {
      $order = $entity;

      // // start Prepare the data for PIMS order object

      $medical_history = NULL; // Initialize the variable before the conditional block.
      if ($order) {
        // Load the medical form reference node.
        if (!$order->get('field_medical_form_refrence')->isEmpty()) {
          $node_target_id = $order->get('field_medical_form_refrence')->target_id;
          // kint($node_target_id);
          $medical_form = Node::load($node_target_id);
          // $medical_form = $this->loadNode($target_id);
          // kint($medical_form);
          if ($medical_form) {
            // Prepare the medical history.
            $medical_history = $this->getNodeMedicalHistory($medical_form);
          }
        }
  
        $data = [
          'customer_info' => $this->getCustomerInfo($order),
          'order_details' => $this->getOrderDetails($order),
          'payment_info' => $this->getPaymentInfo($order),
        ];
        if($medical_history){
          $data['medicalHistory'] = $medical_history;
        }

        // Convert the data array to JSON format.
        $json_data = json_encode($data, JSON_PRETTY_PRINT);

        // Log the JSON object to Drupal's logger.
        $this->logger->info('JSON Data: @json', ['@json' => $json_data]);

        // // post data to PIMS
        // $this->postToPimsApi($json_data);
      }
      // // end Prepare the data for PIMS order object


      // start Prepare the data for Affiliate purchase
      $email = $order->getEmail();
      $purchase_amount = $order->getTotalPrice()->getNumber();
      // $plan_code = 'your_plan_code'; // Replace with your actual plan code

      // Retrieve the affiliate code from the session or cookie.
      $affiliate_code = \Drupal::service('session')->get('affiliate_code') ?? \Drupal::request()->cookies->get('affiliate_code');
      // kint($affiliate_code);
      // exit;
      // Call the LeadDyno Purchase Service to create a new purchase
      $result = $this->leadDynoPurchaseService->createPurchase($email, $affiliate_code, $purchase_amount);

      // $result = $this->leadDynoPurchaseService->createPurchase($email, NULL, $purchase_amount, $plan_code, $affiliate_code);

      if ($result) {
        // Log the successful purchase creation
        $this->logger->info('Successfully created a purchase in LeadDyno: @result', [
          '@result' => print_r($result, TRUE),
        ]);
      } else {
        // Log the failure
        $this->logger->error('Failed to create a purchase in LeadDyno for order ID: @order_id', [
          '@order_id' => $order->id(),
        ]);
      }
      // end Prepare the data for Affiliate purchase


      // start Prepare the data for slot booking
      
      // Loop through all order items.
      foreach ($order->getItems() as $order_item) {
        // Check if the order item type matches 'consult_booking_order_item_type'.
        if ($order_item->bundle() === 'consult_booking_order_item_type') {
          $bookableId = $order_item->get('field_booking_slot_id')->target_id;
          
          // $bookableId = 659;
          $cUser =  \Drupal::currentUser();
          $entityTypeManager = \Drupal::entityTypeManager();
          $bookableOpeningOBJ = $entityTypeManager
              ->getStorage('bookable_calendar_opening_inst')
              ->load($bookableId);

          $contactStorage = $entityTypeManager->getStorage('booking_contact');
          $contact = [
            'email' => $cUser->getEmail(),
            'party_size' => 1,
            'uid' => $cUser->id()
          ];
          $contact['booking_instance'] = [
            'target_id' => $bookableId,
          ];
          $new_contact = $contactStorage->create($contact);

          $violations = $new_contact->validate();

          if ($violations->count() > 0) {
            $validation_errors = [];
            foreach ($violations as $violation) {
              $validation_errors[] = $violation->getMessage();
            }
            print_r($validation_errors);
          }
          else {
            $new_contact->save();
            $sucess_message = $bookableOpeningOBJ->getSuccessMessage();
            print_r($sucess_message);
            
          }
          // exit;
          // Perform your desired logic here.
          \Drupal::logger('mygotdoc_buy_time_slot')->info('Order contains consult booking order item.');
          // You can add further actions, such as sending emails or updating fields.
        }
      }
      // end Prepare the data for slot booking

    //   // if user pic or id is missing send email to user
    //   $config = \Drupal::config('system.site');
    //              $sitelogin = \Drupal::request()->getSchemeAndHttpHost()."/user/login";
    //              $emailFactory = \Drupal::service('email_factory');
    //              $email = $emailFactory->newTypedEmail('mailer_mgtd_builder', 'newUserActivation');
    //              $email->setVariable('user_name', $user->getAccountName());
    //              $email->setVariable('site_name', $config->get('name'));
    //              $email->setVariable('one_time_login_link', $login_link);
    //              $email->setVariable('site_login', $sitelogin);
    //              $email->setTo($user->getEmail());
    //              $email->send();
    }
  }

  /**
   * Gets customer information from the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return array
   *   An array of customer information.
   */
  protected function getCustomerInfo(OrderInterface $order) {
    $customer = $order->getCustomer();
    $billing_profile = $order->getBillingProfile();

    $billing_profile_detail = [];

    if ($billing_profile) {
        $billing_profile_detail['profile_id'] = $billing_profile->id();
        $billing_profile_detail['type'] = $billing_profile->bundle();

        // Get the address field from the billing profile.
        $address_field = $billing_profile->get('address')->first();
        if ($address_field) {
            // Extract the address values.
            $address = $address_field->getValue();

            // Iterate over the address components and store filled values.
            $billing_profile_detail['address'] = [];
            foreach ($address as $key => $value) {
                if (!empty($value)) {
                    $billing_profile_detail['address'][$key] = $value;
                }
            }
        } else {
            $billing_profile_detail['address'] = NULL;
        }
    } else {
        $billing_profile_detail = NULL;
    }

    return [
        'uid' => $customer->id(),
        'email' => $customer->getEmail(),
        'billing_profile' => $billing_profile_detail,
    ];
}

  

  /**
   * Gets order details.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return array
   *   An array of order details.
   */
  protected function getOrderDetails(OrderInterface $order) {
    $items = [];
    foreach ($order->getItems() as $item) {
        $items[] = [
            'title' => $item->getTitle(),
            'quantity' => $item->getQuantity(),
            'unit_price' => $item->getUnitPrice()->__toString(), // Format the unit price
            'total_price' => $item->getTotalPrice()->__toString(), // Format the total price
        ];
    }

    return [
        'order_id' => $order->id(),
        'order_items' => $items,
        'total_amount' => $order->getTotalPrice()->__toString(), // Format the total amount
        'placed_time' => $order->getPlacedTime(),
        // 'coupon_code' => $order->get('coupon_code')->value ?? 'N/A',
    ];
}


  /**
   * Gets payment information for the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   *
   * @return array
   *   An array of payment information.
   */
  protected function getPaymentInfo(OrderInterface $order) {
    $payment_gateway = NULL;
    $payment_status = NULL;
    $paid_amount = 0;
    $balance = $order->getTotalPrice()->getNumber(); // Total amount as the initial balance.

    // Load payments related to the order.
    $payments = \Drupal::entityTypeManager()->getStorage('commerce_payment')->loadByProperties([
      'order_id' => $order->id(),
    ]);

    if (!empty($payments)) {
        foreach ($payments as $payment) {
            // Get the payment gateway label.
            $payment_gateway = $payment->getPaymentGateway()->getPlugin()->getDisplayLabel();
            
            // Get the payment state.
            $payment_status = $payment->getState()->getLabel();
            
            // Add the paid amount.
            $paid_amount += $payment->getAmount()->getNumber();
        }
    }

    // Calculate the remaining balance.
    $balance -= $paid_amount;

    return [
        'payment_gateway' => $payment_gateway,
        'payment_status' => $payment_status,
        'paid_amount' => $paid_amount,
        'remaining_balance' => $balance,
    ];
  }
  
  /**
 * Load and format the medical history from the 'field_medical_form_inputs' paragraph in a node.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The node entity.
 *
 * @return array
 *   The formatted medical history.
 */
protected function getNodeMedicalHistory(NodeInterface $node) {
  $medical_history = [];

  // Check if the node has the 'field_medical_form_inputs' field.
  if (!$node->hasField('field_medical_form_inputs') || $node->get('field_medical_form_inputs')->isEmpty()) {
    return $medical_history; // Return early if the field is missing or empty.
  }

  // Load the referenced paragraph entities.
  $paragraphs = $node->get('field_medical_form_inputs')->referencedEntities();

  // Retrieve the dependent fields for this paragraph type.
  if (!empty($paragraphs)) {
    $entity_type = 'paragraph';
    $bundle = $paragraphs[0]->bundle(); // Assuming all paragraphs share the same bundle.
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $dependent_fields = $this->getDependentFields($entity_type_manager, $entity_type, $bundle);
    // Iterate over each paragraph and load its fields.
    foreach ($paragraphs as $paragraph) {
      if ($paragraph instanceof Paragraph) {
        $field_data = [];

        // Iterate over field definitions, ignore base fields and empty values.
        foreach ($paragraph->getFieldDefinitions() as $field_name => $field_definition) {
          // Skip base fields.
          if ($field_definition->getFieldStorageDefinition()->isBaseField()) {
            continue;
          }

          // Get the field value.
          $field_value = $paragraph->get($field_name)->value;
          $field_label = (string) $field_definition->getLabel();

          // Skip fields with null or empty values.
          if (empty($field_value)) {
            continue;
          }

          // Check if this field is a dependent field or a parent field.
          if (!in_array($field_name, $dependent_fields)) {
            // Remove special characters first, then replace spaces with underscores, and convert to lowercase.
            $modified_field_label = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9 ]/', '', $field_label)));
            // This is a parent field.
            $field_data[$modified_field_label] = $field_value;
          }

          // Check if this field is a parent field (exists in $dependent_fields).
          if (isset($dependent_fields[$field_name])) {
            // Get the corresponding dependent (conditional) field.
            $dependent_field_name = $dependent_fields[$field_name];

            // Check if the conditional field has a value.
            if ($paragraph->hasField($dependent_field_name) && !$paragraph->get($dependent_field_name)->isEmpty()) {
              // Get the conditional field's value and label.
              $dependent_field_value = $paragraph->get($dependent_field_name)->value;
              $dependent_field_definition = $paragraph->getFieldDefinition($dependent_field_name);
              $dependent_field_label = (string) $dependent_field_definition->getLabel();
              // Append the conditional field label and its value.
              // Replace white spaces with underscores, convert to lowercase, and remove special characters.
              $modified_dependent_field_label = strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z0-9 ]/', '', $dependent_field_label)));
              $field_data[$modified_dependent_field_label] = $dependent_field_value;
            }
          }
        }
        // Add the field data to the medical history.
        if (!empty($field_data)) {
          $medical_history = $field_data;
        }
      }
    }
  }

  return $medical_history;
}



  /**
   * Gets the dependent fields for a given entity type and bundle.
   */
  protected function getDependentFields(EntityTypeManagerInterface $entity_type_manager, $entity_type, $bundle) {
    $form_display_entity = $entity_type_manager
      ->getStorage('entity_form_display')
      ->load("$entity_type.$bundle.default");
  
    $dependent_fields = [];
  
    if ($form_display_entity) {
      $fields = $form_display_entity->getComponents();
  
      foreach ($fields as $field_name => $field) {
        // Check if this field has conditional (dependent) fields.
        if (!empty($field['third_party_settings']['conditional_fields'])) {
          // Get the conditional field data.
          $conditional_field_data = $field['third_party_settings']['conditional_fields'];
  
          // Loop through the conditional field data to get the parent (dependee) and dependent field.
          foreach ($conditional_field_data as $key => $value) {
            // Get the parent field (dependee).
            $parent_field = $value['dependee'];
            
            // Append the dependent field under its parent field.
            $dependent_fields[$parent_field] = $field_name;
          }
        }
      }
    }
    return $dependent_fields;
  }

  /**
   * Helper function to send the data to PIMS API.
   *
   * @param string $json_data
   *   The JSON data to post.
   */
  protected function postToPimsApi($json_data) {
    try {
      // Get access token from PIMS.
      $response = $this->httpClient->request('POST', "http://48.216.140.246:3000/auth/token");
      $body = $response->getBody()->getContents();
      $results = json_decode($body);
      $token = $results->token;
    }
    catch (RequestException $e) {
      $err = $e->getResponse()->getBody()->getContents();
      $this->logger->error('Error fetching token from PIMS API: @message', ['@message' => $err]);
      return;
    }

    try {
      // Post the order data to PIMS.
      $responseUserObj = $this->httpClient->request('POST', "http://48.216.140.246:3000/getTimeSlot", [
        'headers' => [
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
          'Authorization' => 'Bearer ' . $token,
        ],
        'json' => $json_data,
      ]);
      $bodyResponseUserObj = $responseUserObj->getBody()->getContents();
      $jsonObjectFromPims = json_decode($bodyResponseUserObj);

      $this->logger->info('Successfully posted data to PIMS API. Status code: @code', ['@code' => $responseUserObj->getStatusCode()]);
    }
    catch (RequestException $e) {
      $err = $e->getResponse()->getBody()->getContents();
      $this->logger->error('Error posting data to PIMS API: @message', ['@message' => $err]);
    }
  }

}
