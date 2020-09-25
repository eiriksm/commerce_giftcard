<?php

namespace Drupal\commerce_giftcard\Entity;

use Drupal\commerce\EntityOwnerTrait;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the commerce gift card entity class.
 *
 * @ContentEntityType(
 *   id = "commerce_giftcard_transaction",
 *   label = @Translation("Gift card transaction"),
 *   label_collection = @Translation("Gift card transactions"),
 *   label_singular = @Translation("Gift card transaction"),
 *   label_plural = @Translation("Gift card transactions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count gift card transaction",
 *     plural = "@count gift card transactions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\commerce_giftcard\GiftcardTransactionListBuilder",
 *     "access" = "Drupal\commerce_giftcard\GiftcardTransactionAccessControlHandler",
 *     "storage_schema" = "Drupal\commerce_giftcard\GiftcardTransactionStorageSchema",
 *     "views_data" = "Drupal\commerce\CommerceEntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\commerce_giftcard\Form\GiftcardTransactionForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer commerce_giftcard",
 *   base_table = "commerce_giftcard_transaction",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid"
 *   },
 *   links = {
 *     "add-form" = "/admin/commerce/giftcards/add-transaction",
 *   },
 * )
 */
class GiftcardTransaction extends ContentEntityBase implements GiftcardTransactionInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAmount() {
    if (!$this->get('amount')->isEmpty()) {
      return $this->get('amount')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setAmount(Price $price) {
    $this->set('amount', $price);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGiftcardId() {
    return $this->get('giftcard')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getGiftCard() {
    return $this->get('giftcard')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['uid']
      ->setLabel(t('Owner'))
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      // Giftcard transactions do not automatically belong to the current user.
      ->setDefaultValueCallback(NULL);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created on'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'));

    $fields['giftcard'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Gift card'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'commerce_giftcard')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['amount'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Amount'))
      ->addConstraint('GiftcardTransactionValidAmount')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'commerce_price_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // If this is a new transaction and there is an amount change,
    // update the giftcard.
    if ($this->isNew() && $this->getAmount() && !$this->getAmount()->isZero() && $this->getGiftCard()) {
      $giftcard = $this->getGiftCard();

      $new_balance = $giftcard->getBalance()->add($this->getAmount());
      if ($new_balance->isNegative()) {
        throw new \Exception('Giftcard balance must not be negative');
      }
      $giftcard->setBalance($new_balance);
      $giftcard->save();
    }
  }

}
