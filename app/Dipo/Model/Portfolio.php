<?php
namespace Dipo\Model;

class Portfolio
{
  private $_groups = array();
  private $_tags = array();

  public function addGroup(Group $group)
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

  public function addTag(Tag $tag)
  {
    // TODO check if code is not used as tag or group
    $this->_tags[$tag->getCode()] = $tag;
  }

  /* Return all tags used in the portfolio alphabetically (numeric tags last)
   */
  public function getTagsSorted()
  {
    $tags = $this->_tags;
    usort($tags, function ($a, $b) {
      if (is_numeric($a->getName()) && !is_numeric($b->getName())) {
        return 1;
      }
      if (!is_numeric($a->getName()) && is_numeric($b->getName())) {
        return -1;
      }
      return strcasecmp($a->getName(), $b->getName());
    });

    return $tags;
  }

}
