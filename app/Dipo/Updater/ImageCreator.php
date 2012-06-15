<?php
namespace Dipo\Updater;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class ImageCreator
{

  private static $content_to_web_types = array(
      'tiff' => 'jpeg'
    );

  private $content_path;
  private $web_path;
  private $maximum_box;
  private $imagine_driver;

  public function __construct($content_path, $web_path, $maximum_width, $maximum_height, $imagine_driver)
  {
    $this->content_path = $content_path;
    $this->web_path = $web_path;
    $this->imagine_driver = $imagine_driver;
    $this->maximum_box = new \Imagine\Image\Box($maximum_width, $maximum_height);
  }

  private function getContentFile($group, $code)
  {
    /* Automatically determine extension of file */
    $content_path = $this->content_path . '/'. $group->getCode();

    $finder = new Finder();
    $finder->in($content_path)->depth(0)->name('/^' . $code . '\..*/');

    $files = $finder->getIterator();
    $files->rewind();
    $content_file = $files->current();

    if ($content_file === NULL)
      throw new Exception(array(
        'action' => 'content-image',
        'error' => 'file-missing'
      ));

    $files->next();
    if ($files->valid()) {
      throw new Exception(array(
        'action' => 'content-image',
        'error' => 'multiple-files'
      ));
    }

    return $content_file;
  }

  private function getWebFilepathAndType($content_file, $group, $code, $metadata)
  {
    $content_extension = $content_file->getExtension();
    $content_type = \Dipo\Model\Image::getTypeForExtension(strtolower($content_extension));

    $auto_web_type = array_key_exists($content_type, self::$content_to_web_types) ? self::$content_to_web_types[$content_type] : $content_type;
    $web_type = strtolower($metadata->getString('web-type', $auto_web_type));
    // TODO validate web-type to be valid
    $web_extension = \Dipo\Model\Image::getExtensionForType($web_type);

    $web_filepath = $this->web_path . '/' . $group->getCode() . '/' . $code . '.' .$web_extension;

    return array($web_filepath, $web_type);
  }

  private function createWebFile($content_file, $web_filepath, $metadata)
  {
    $filesystem = new Filesystem();

    $imagine_class = sprintf('\Imagine\%s\Imagine', $this->imagine_driver);
    $imagine = new $imagine_class();

    try {
      $content_image = $imagine->open($content_file->__toString());
    } catch (\Imagine\Exception\InvalidArgumentException $e) {
      throw new Exception(array(
        'action' => 'content-image',
        'error' => 'open-error',
        'exception_message' => $e->getMessage()
      ));
    }

    $size = $content_image->getSize();

    if ($this->maximum_box->contains($size)) {
      /* No resizing needed. Copy the content */
      $filesystem->copy($content_file, $web_filepath, true);
    } else {
      /* The content file is bigger than the maximum size: resize it an save */
      $filesystem->mkdir(dirname($web_filepath));
      $resized_image = $content_image->thumbnail($this->maximum_box);
      $resized_image->save($web_filepath);
      $size = $resized_image->getSize();
    }

    return array($size->getWidth(), $size->getHeight());
  }

  private function addMetadata($image, $metadata)
  {
    try {
      if ($metadata->has('description')) {
        $image->setDescription($metadata->getMarkdownAsHtml('description'));
      }
    } catch (Exception $e) {
      throw $e->addDetails(array('action' => 'metadata-element'));
    }
  }

  public function create($group, $code, $metadata)
  {
    try {
      $content_file = $this->getContentFile($group, $code);
      list($web_filepath, $web_type) = $this->getWebFilepathAndType($content_file, $group, $code, $metadata);
      list($width, $height) = $this->createWebFile($content_file, $web_filepath, $metadata);

      $image = new \Dipo\Model\Image($code, $width, $height, $web_type);
      $this->addMetadata($image, $metadata);

      return $image;
    } catch (Exception $e) {
      throw $e->addDetails(array(
        'group' => $group->getCode(),
        'element' => $code
      ));
    }
  }

}
