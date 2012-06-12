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

  public function getCode()
  {
    return $this->_code;
  }

  public function getName()
  {
    return $this->_name;
  }

  public function addElement($element)
  {
    parent::addElement($element);
    $element->addTag($this);
  }

}
