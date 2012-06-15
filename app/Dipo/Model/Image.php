<?php
namespace Dipo\Model;

class Image extends Element
{
  private static $type_extension = array(
      'jpeg' => 'jpg',
      'png' => 'png',
      'gif' => 'gif',
      'tiff' => 'tif'
    );

  private $image_type;

  public function __construct($width, $height, $description, $image_type)
  {
    parent::__construct($width, $height, $description);
    $this->image_type = $image_type;
  }

  public function getElementType()
  {
    return 'image';
  }

  public function getImageType()
  {
    return $this->image_type;
  }

  public function getPublicFileExtension()
  {
    return self::getExtensionForType($this->image_type);
  }

  public function getPublicFilePath()
  {
    return $this->getGroup()->getCode() . '/' . $this->getCode() . '.' . $this->getPublicFileExtension();
  }

  public static function getExtensionForType($type) {
    return array_key_exists($type, self::$type_extension) ? self::$type_extension[$type] : null;
  }

  public static function getTypeForExtension($extension) {
    return array_search($extension, self::$type_extension);
  }

}
