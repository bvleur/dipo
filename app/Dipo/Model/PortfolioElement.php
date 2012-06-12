<?php
namespace Dipo\Model;

abstract class PortfolioElement
{

  private $_code;
  private $_width;
  private $_height;
  private $_description;
  private $_group;
  private $_tags;

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

  public function addTag($tag)
  {
    $this->_tags[] = $tag;
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
