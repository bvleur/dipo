<?php
class Portfolio
{
  private $_groups = array();

  public function addGroup(PortfolioGroup $group)
  {
    $this->_groups[$group->getCode()] = $group;
  }

  /**
   * Get an array of groups ordered by:
   *  - ascending set index (set 1, set 2)
   *  - descending created at date (newest fist)
   */
  public function getGroupsSorted()
  {
    $groups = $this->_groups;
    usort($groups, function ($a, $b) {
      if ($a->getSetIndex() !== $b->getSetIndex())
        return $a->getSetIndex() - $b->getSetIndex();

      return $a->getCreatedAt() < $b->getCreatedAt() ? 1 : -1;
    });

    return $groups;
  }

  public function getGroupByCode($code)
  {
    if (!array_key_exists($code, $this->_groups))
      return null;

    return $this->_groups[$code];
  }

}

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

class PortfolioDuplicateElementCodeException extends Exception
{

  public function __construct($group, $new_element, $existing_element)
  {
    // TODO Implement Exception interface
  }

}

abstract class PortfolioElement
{

  private $_code;
  private $_width;
  private $_height;
  private $_description;
  private $_group;

  public function __construct($code, $width, $height)
  {
    $this->_code = $code;
    $this->_width = $width;
    $this->_height = $height;
  }

  public function getCode()
  {
    return $this->_code;
  }

  public function getWidth()
  {
    return $this->_width;
  }

  public function getHeight()
  {
    return $this->_height;
  }

  public function setGroup($group)
  {
    $this->_group = $group;
  }

  public function getGroup()
  {
    return $this->_group;
  }

  public function setDescription($description)
  {
    $this->_description = $description;
  }

  public function getDescription()
  {
    if ($this->_description !== null)
      return $this->_description;

    return $this->_group->getDescription();
  }

}

class PortfolioImage extends PortfolioElement
{

  public function __construct($width, $height, $description)
  {
    parent::__construct($width, $height, $description);
  }

  public function getElementType()
  {
    return 'image';
  }

  public function getPublicFileExtension()
  {
    return 'jpg'; //TODO
  }

  public function getPublicFilePath()
  {
    return $this->getGroup()->getCode() . '/' . $this->getCode() . '.' . $this->getPublicFileExtension();
  }

}
