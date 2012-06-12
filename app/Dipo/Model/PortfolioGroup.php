<?php
namespace Dipo\Model;

class PortfolioGroup extends PortfolioElementContainer
{

  private $_set_index;
  private $_code;
  private $_created_at;
  private $_name;

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
    parent::addElement($element);
    $element->setGroup($this);
  }

}
