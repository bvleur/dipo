<?php
namespace Dipo\Model;

class Portfolio
{
  private $_groups = array();
  private $_tags = array();

  public function addGroup(PortfolioGroup $group)
  {
    // TODO check if code is not used as tag or group
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

  public function getContainerByCode($code)
  {
    if (array_key_exists($code, $this->_groups))
      return $this->_groups[$code];

    if (array_key_exists($code, $this->_tags))
      return $this->_tags[$code];

    return null;
  }

  public function addTag(PortfolioTag $tag)
  {
    // TODO check if code is not used as tag or group
    $this->_tags[$tag->getCode()] = $tag;
  }

}
