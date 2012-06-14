<?php
namespace Dipo\Model;

class Tag extends ElementContainer
{

  private $_code;
  private $_name;

  public function __construct($code, $name)
  {
    $this->_code = $code;
    $this->_name = $name;
  }

  public function getElementId($element)
  {
    return $element->getGroup()->getCode() . '.' . $element->getCode();
  }

  public function getCode()
  {
    return $this->_code;
  }

  public function getName()
  {
    return $this->_name;
  }

}
