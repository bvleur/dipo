<?php
namespace Dipo\Updater;

use dflydev\markdown\MarkdownParser;

class Metadata
{

  private $metadata;
  private $parent;

  public function __construct($metadata, $parent = null)
  {
    $this->metadata = (array)$metadata;
    $this->parent = $parent;
  }

  public function getParent()
  {
    return $this->parent;
  }

  public function getCount()
  {
    return count($this->metadata);
  }

  public function getKeys()
  {
    return array_keys($this->metadata);
  }

  public function getChild($key)
  {
    //TODO check if $key exists?
    return new Metadata($this->metadata[$key], $this);
  }

  public function has($key)
  {
    return array_key_exists($key, $this->metadata);
  }

  public function getString($key, $default = null)
  {
    // TODO Figure out non-empty case
    $value = $this->getRequiredValue($key, $default);
    return (string)$value;
  }

  public function getInteger($key, $default = null)
  {
    // TODO Figure out detecting non-integers and error handling.
    $value = $this->getRequiredValue($key, $default);
    return (integer)$value;
  }

  public function getDateTime($key, $default = null)
  {
    $value = $this->getRequiredValue($key, $default);
    // TODO Figure out error handling.
    return new \DateTime($value);
  }

  public function getMarkdownAsHtml($key, $default = null)
  {
    $value = $this->getRequiredValue($key, $default);
    // TODO Figure out error handling.
    $markdownParser = new \dflydev\markdown\MarkdownParser();
    return $markdownParser->transformMarkdown($value);
  }

  public function getArray($key, $default = null)
  {
    $value = $this->getRequiredValue($key, $default);
    return (array)$value;
  }

  /* If the key is missing and no default is supplied (default == null):
   *   - Throw an exception
   * If the key is missing and a default is supplied (default != null):
   *   - Return the default
   * If the key exists:
   *   - Return the value
   */
  private function getRequiredValue($key, $default = null)
  {
    if (array_key_exists($key, $this->metadata))
      return $this->metadata[$key];

    if ($default === null) {
      throw new Exception(array(
        'error' => 'missing',
        'key' => $key,
        'data_type' => $type
      ));
    } else {
      return $default;
    }
  }

}
