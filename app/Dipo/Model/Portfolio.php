<?php
namespace Dipo\Model;

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
