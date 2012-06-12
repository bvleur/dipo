<?php
namespace Dipo\Model;

abstract class ElementContainer
{

  private $_elements = array();

  public function addElement($element)
  {
    $code = $element->getCode();

    if (array_key_exists($code, $this->_elements))
      throw new DuplicateElementCodeException($this, $element, $this->_elements[$code]);

    $this->_elements[$element->getCode()] = $element;
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
