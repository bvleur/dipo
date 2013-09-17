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

  public function getFriendlyBoolean($key, $default = null)
  {
    $value = strtolower($this->getRequiredValue($key, $default));
    if (in_array($value, array('yes', 'y', 'true', '1'))) {
      return true;
    } elseif (in_array($value, array('no', 'n', 'false', '0'))) {
      return false;
    }

    /* Value could not be matched as positive or negative value */
    throw new Exception(array(
      'error' => 'invalid-boolean-value',
      'key' => $key,
      'data_type' => $type
    ));
  }

  public function getMarkdownAsHtml($key, $default = null)
  {
    $value = $this->getRequiredValue($key, $default);

    /* The MarkDown Parser returns a non-empty string for empty inputs which
     * makes it hard to detect empty values in HTML
     * Detect empty input and force return a empty string.
     */
    if (strlen(trim($value)) === 0) {
        return '';
    }

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
