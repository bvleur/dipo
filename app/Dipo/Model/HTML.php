<?php
namespace Dipo\Model;

class HTML extends Element
{
  private $_html;

  public function __construct($code, $width, $height, $html)
  {
    parent::__construct($code, $width, $height);
    $this->_html = $html;
  }

  public function getElementType()
  {
    return 'html';
  }

  public function getHTML()
  {
    return $this->_html;
  }

}
