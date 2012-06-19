<?php
namespace Dipo\Model;

abstract class ElementContainer
{

  private $_elements = array();

  abstract public function getElementId($element);

  public function addElement($element)
  {
    $id = $this->getElementId($element);

    if (array_key_exists($id, $this->_elements)) {
      throw new DuplicateCodeException($this, $element, $this->_elements[$id]);
    }

    $this->_elements[$id] = $element;
  }

  public function getElements()
  {
    return $this->_elements;
  }

  public function getElement($id)
  {
    if (!array_key_exists($id, $this->_elements))
      return null;

    return $this->_elements[$id];
  }

  public function getElementCount()
  {
    return count($this->_elements);
  }

  public function getFirstElement()
  {
    return reset($this->_elements);
  }

  public function getElementByIndex($index)
  {
    if ($index >= 0 && $index < count($this->_elements)) {
      $ids = array_keys($this->_elements);
      return $this->_elements[$ids[$index]];
    }

    return null;
  }

  public function getNextElement($element)
  {
    return $this->getElementByIndex($this->getIndexOfElement($element) + 1);
  }

  public function getPreviousElement($element)
  {
    return $this->getElementByIndex($this->getIndexOfElement($element) - 1);
  }

  public function getIndexOfElement($element)
  {
    $id = $this->getElementId($element);
    $keys = array_flip(array_keys($this->_elements));
    if (!array_key_exists($id, $keys))
      return null;

    return $keys[$id];
  }

}
