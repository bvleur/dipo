<?php
namespace Dipo;

class PortfolioUpdaterException extends \Exception
{
  private $details;

  public function __construct($details)
  {
    $this->details = $details;
  }

  public function addDetails($new)
  {
    $this->details = array_merge($this->details, $new);
    return $this;
  }

  public function getDetails()
  {
    return $this->details;
  }
}
