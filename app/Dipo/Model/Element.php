<?php
namespace Dipo\Model;

abstract class Element
{

  private $_code;
  private $_container_size_code;
  private $_width;
  private $_height;
  private $_description;
  private $_group;
  private $_tags = array();

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
    $tag->addElement($this);
  }

  public function getTags()
  {
    return $this->_tags;
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

  public function setContainerSizeCode($containerSizeCode)
  {
    $this->_container_size_code = $containerSizeCode;
  }

  public function getContainerSizeCode()
  {
    if ($this->_container_size_code !== null) {
      return $this->_container_size_code;
    }

    return $this->_group->getContainerSizeCode();
  }

}
