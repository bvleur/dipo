<?php
$app['portfolio'] = $app->share(function () {
  $portfolio = new Portfolio();

  $pg = new PortfolioGroup('de-zwarte-kat', new DateTime('2011-01-01'), 'De Zwarte Kat');
  $portfolio->addGroup($pg);
  $pg = new PortfolioGroup('the-generic-city', new DateTime('2011-02-01'), 'The Generic City');
  $portfolio->addGroup($pg);
  $pg = new PortfolioGroup('chips', new DateTime('2010-08-10'), 'Chips');
  $portfolio->addGroup($pg);

  $pg = new PortfolioGroup('schetsboek', new DateTime('2011-01-01'), 'Schetsboek', 2);
  $portfolio->addGroup($pg);
  $pg = new PortfolioGroup('random', new DateTime('2011-02-01'), 'Random', 2);
  $portfolio->addGroup($pg);

  return $portfolio;
});

class Portfolio
{
  private $_groups = array();

  public function addGroup(PortfolioGroup $group)
  {
    $this->_groups[] = $group;
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

}

class PortfolioGroup
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

}
