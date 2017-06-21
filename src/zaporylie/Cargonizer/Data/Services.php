<?php

namespace zaporylie\Cargonizer\Data;

class Services extends ObjectsWrapper implements SerializableDataInterface {

  /**
   * @param \zaporylie\Cargonizer\Data\Service $item
   *
   * @return self
   */
  public function addItem(Service $item) {
    $this->array[$item->getIdentifier()] = $item;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function fromXML(\SimpleXMLElement $xml) {
    $services = new Services();

    foreach ($xml as $item) {
      $services->addItem(Service::fromXML($item));
    }

    return $services;
  }

  /**
   * {@inheritdoc}
   */
  public function toXML(\SimpleXMLElement $xml) {
    $attributes = $xml->addChild('attributes');
    $attributes->addAttribute('type', 'array');
    foreach ($this as $attribute) {
      $attribute->toXML($attributes);
    }
    return $xml;
  }
}
