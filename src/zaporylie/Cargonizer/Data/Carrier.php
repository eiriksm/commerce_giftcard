<?php

namespace zaporylie\Cargonizer\Data;

class Carrier {

  /**
   * @var string
   */
  protected $identifier;

  /**
   * @var string
   */
  protected $name;

  /**
   * @param string $name
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * @param string $identifier
   */
  public function setIdentifier($identifier) {
    $this->identifier = $identifier;
  }

  /**
   * @param \SimpleXMLElement $xml
   *
   * @return \zaporylie\Cargonizer\Data\Carrier
   */
  public static function unserialize(\SimpleXMLElement $xml) {
    $carrier = new Carrier();
    $carrier->setName((string) $xml->name);
    $carrier->setIdentifier((string) $xml->identifier);
    return $carrier;
  }
}