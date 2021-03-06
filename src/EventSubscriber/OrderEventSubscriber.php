<?php

namespace Drupal\commerce_giftcard\EventSubscriber;

use Drupal\commerce_giftcard\Entity\GiftcardInterface;
use Drupal\commerce_giftcard\GiftcardCodeGenerator;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;

/**
 * Order event subscriber.
 */
class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The gift card code generator.
   *
   * @var \Drupal\commerce_giftcard\GiftcardCodeGenerator
   */
  protected $codeGenerator;

  /**
   * OrderEventSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *  The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, GiftcardCodeGenerator $code_generator) {
    $this->entityTypeManager = $entity_type_manager;
    $this->codeGenerator = $code_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.place.pre_transition' => 'orderPlaced',
    ];
    return $events;
  }

  /**
   * Listens on the order placed event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function orderPlaced(WorkflowTransitionEvent $event) {
    $this->registerUsage($event->getEntity());
    $this->giftcardPurchase($event->getEntity());
  }

  /**
   * Registers giftcard usage when the order is placed.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  protected function registerUsage(OrderInterface $order) {
    $adjustments = $order->collectAdjustments();
    foreach ($adjustments as $adjustment) {
      if ($adjustment->getType() != 'commerce_giftcard' || !$adjustment->getSourceId() || $adjustment->getAmount()->isZero()) {
        continue;
      }

      $giftcard = $this->entityTypeManager->getStorage('commerce_giftcard')->load($adjustment->getSourceId());

      // Create a transaction for each used giftcard.
      if ($giftcard instanceof GiftcardInterface) {

        /** @var \Drupal\commerce_giftcard\Entity\GiftcardTransactionInterface $transaction */
        $transaction = $this->entityTypeManager->getStorage('commerce_giftcard_transaction')->create([
          'giftcard' => $giftcard->id(),
          'amount' => $adjustment->getAmount(),
        ]);
        $transaction->save();
      }
    }
  }

  /**
   * Creates giftcard when entity with giftcard amount is purchased.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  protected function giftcardPurchase(OrderInterface $order) {
    $items = $order->getItems();

    foreach ($items as $item) {
      $purchased_entity = $item->getPurchasedEntity();
      if ($purchased_entity && $purchased_entity->hasField('commerce_giftcard_amount') && $purchased_entity->hasField('commerce_giftcard_type') && !$purchased_entity->get('commerce_giftcard_amount')->isEmpty()) {

        $codes = $this->codeGenerator->generateCodes($purchased_entity->get('commerce_giftcard_type')->entity, $item->getQuantity());

        for ($i = 0; $i < $item->getQuantity(); $i++) {
          // Create a giftcard and then add the balance as a transaction to
          // store the reference to this order.

          $amount = $purchased_entity->get('commerce_giftcard_amount')->first()->toPrice();
          $giftcard = $this->entityTypeManager->getStorage('commerce_giftcard')->create([
            'type' => $purchased_entity->get('commerce_giftcard_type')->target_id,
            'code' => $codes[$i],
            'balance' => new Price(0, $amount->getCurrencyCode()),
            'uid' => $order->getCustomerId(),
            // Set the stores to the stores that the purchasable entity can be
            // bought not just the one from the order.
            // @todo Make this configurable, add an event for the giftcard and
            //   transaction being created?
            'stores' => $purchased_entity->getStores(),
          ]);
          $giftcard->save();

          $transaction = $this->entityTypeManager->getStorage('commerce_giftcard_transaction')->create([
            'giftcard' => $giftcard->id(),
            'amount' => $amount,
            'reference_type' => $item->getEntityTypeId(),
            'reference_id' => $item->id(),
            'comment' => 'Bought @product.',
            'variables' => ['@product' => $purchased_entity->label()]
          ]);
          $transaction->save();
        }
      }
    }
  }

}
