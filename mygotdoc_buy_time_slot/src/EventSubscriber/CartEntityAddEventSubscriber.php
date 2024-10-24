<?php

namespace Drupal\mygotdoc_buy_time_slot\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartOrderItemAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class CartEntityAddEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CartEventSubscriber object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(MessengerInterface $messenger, TranslationInterface $string_translation, EntityTypeManagerInterface $entity_type_manager) {
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      CartEvents::CART_ORDER_ITEM_ADD => 'displayAddToCartMessage',
    ];
    return $events;
  }

  /**
   * Displays an add to cart message.
   *
   * @param \Drupal\commerce_cart\Event\CartOrderItemAddEvent $event
   *   The add to cart event.
   */
  public function displayAddToCartMessage(CartOrderItemAddEvent $event) {
    $cart = $event->getCart();
    $orderItem = $event->getOrderItem();
    $cart_items = $cart->getItems();
    $entity = $orderItem->getPurchasedEntity();
    

    foreach ($cart_items as $cart_item) {
      $bundle = $cart_item->getPurchasedEntity();
      /*if($bundle->bundle() == $entity->bundle()){
        $cart->removeItem($cart_item);
        $cart_item->delete();
      }*/
      /*if ($cart_item->id() != $orderItem->id()) {
        $cart->removeItem($cart_item);
        $cart_item->delete();
      }*/
    }
    //$cart->save();

    
    /*$order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    // @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type 
    $order_type = $order_type_storage->load($entity->bundle());

    if ($order_type->getThirdPartySetting('commerce_cart', 'enable_cart_message', TRUE)) {
      $this->messenger->addMessage($this->t('@entity added to @bundle <a href=":url">your cart</a>.', [
        '@entity' => $event->getItems()->label() . " custom",
        ':url' => Url::fromRoute('commerce_cart.page')->toString(),
        '@bundle' => $order_type->type(),
      ]));
    }*/
  }
}
