<?php
namespace Dipo\Model;

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
