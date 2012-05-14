<?php
namespace Dipo\Model;

class PortfolioGroup
{

  private $_set_index;
  private $_code;
  private $_created_at;
  private $_name;
  private $_elements = array();

  public function __construct($code, $created_at, $name, $set_index = 1)
  {
    $this->_code = $code;
    $this->_created_at = $created_at;
    $this->_name = $name;
    $this->_set_index = $set_index;
  }

  public function getSetIndex()
  {
    return $this->_set_index;
  }

  public function getCode()
  {
    return $this->_code;
  }

  public function getName()
  {
    return $this->_name;
  }

  public function getCreatedAt()
  {
    return $this->_created_at;
  }

  public function setDescription($description)
  {
    $this->_description = $description;
  }

  public function getDescription()
  {
    return $this->_description;
  }

  public function addElement($element)
  {
    $code = $element->getCode();

    if (array_key_exists($code, $this->_elements))
      throw new PortfolioDuplicateElementCodeException($group, $element, $this->_elements[$code]);

    $this->_elements[$element->getCode()] = $element;
    $element->setGroup($this);
  }

  public function getElements()
  {
    return $this->_elements;
  }

  public function getElement($code)
  {
    if (!array_key_exists($code, $this->_elements))
      return null;

    return $this->_elements[$code];
  }

  public function getElementCount()
  {
    return count($this->_elements);
  }

  public function getFirstElement()
  {
    return reset($this->_elements);
  }

  public function getIndexOfElement($element)
  {
    $keys = array_flip(array_keys($this->_elements));
    if (!array_key_exists($element->getCode(), $keys))
      return null;

    return $keys[$element->getCode()];
  }

}
