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
  private $container_boxes;
  private $imagine_driver;

  public function __construct($content_path, $web_path, $container_sizes, $imagine_driver)
  {
    $this->content_path = $content_path;
    $this->web_path = $web_path;
    $this->imagine_driver = $imagine_driver;
    $this->container_boxes = array_map(function ($container_size) {
        return new \Imagine\Image\Box($container_size['width'], $container_size['height']);
    }, $container_sizes);
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

  private function createWebFile($content_file, $web_filepath, $metadata, $container_size_code)
  {
    $filesystem = new Filesystem();

    $imagine_class = sprintf('\Imagine\%s\Imagine', $this->imagine_driver);
    $imagine = new $imagine_class();

    try {
      $image = $imagine->open($content_file->__toString());
    } catch (\Imagine\Exception\InvalidArgumentException $e) {
      throw new Exception(array(
        'action' => 'content-image',
        'error' => 'open-error',
        'exception_message' => $e->getMessage()
      ));
    }

    $manipulated = false;

    /* Auto-rotate if needed */
    $exif = @exif_read_data($content_file);
    if ($exif !== false) {
      if (array_key_exists('IFD0', $exif) && array_key_exists('Orientation', $exif['IFD0'])) {
        $orientation = $exif['IFD0']['Orientation'];
      } elseif (array_key_exists('Orientation', $exif)) {
        $orientation = $exif['Orientation'];
      }
      if (isset($orientation) && $orientation !== 1) {
        $manipulated = true;

        /* Orientations according to: http://sylvana.net/jpegcrop/exif_orientation.html
         *   1        2       3      4         5            6           7          8
         *  888888  888888      88  88      8888888888  88                  88  8888888888
         *  88          88      88  88      88  88      88  88          88  88      88  88
         *  8888      8888    8888  8888    88          8888888888  8888888888          88
         *  88          88      88  88
         *  88          88  888888  888888
         */
        switch ($orientation) {
        case 2:
          $image->flipHorizontally();
          break;
        case 3:
          $image->rotate(180);
          break;
        case 4:
          $image->flipVertically();
          break;
        case 6:
          $image->rotate(90);
          break;
        case 8:
          $image->rotate(270);
          break;
        }
      }
    }
    /* If the content file is bigger than the container: resize it */
    $container_box = $this->container_boxes[$container_size_code];
    $size = $image->getSize();
    if (!$container_box->contains($size)) {
      $manipulated = true;
      $image = $image->thumbnail($container_box);
      $size = $image->getSize();
    }

    // TODO don't copy original if web_type is not content_type (even if no resize is needed)

    /* Copy original if no manipulations are done or save manipulated otherwise */
    if (!$manipulated) {
      $filesystem->copy($content_file, $web_filepath, true);
    } else {
      $filesystem->mkdir(dirname($web_filepath));
      $image->save($web_filepath);
    }

    return array($size->getWidth(), $size->getHeight());
  }

  private function addMetadata($image, $metadata, $container_size_code)
  {
    try {
      if ($metadata->has('description')) {
        $image->setDescription($metadata->getMarkdownAsHtml('description'));
      }
      if ($metadata->has('container-size')) {
        $image->setContainerSizeCode($container_size_code);
      }
    } catch (Exception $e) {
      throw $e->addDetails(array('action' => 'metadata-element'));
    }
  }

  public function getContainerSizeCode($group, $metadata)
  {
    /* Prefer to use a container size defined specifically for this image */
    if ($metadata->has('container-size')) {
      $container_size_code = $metadata->getString('container-size');
      if (!array_key_exists($container_size_code, $this->container_boxes)) {
          throw new Exception(array(
            'action' => 'metadata-element',
            'error' => 'unknown-container-size',
            'containerSizeCode' => $container_size_code
          ));
      }
      return $container_size_code;
    }

    /* Use the container size of this group otherwise */
    return $group->getContainerSizeCode();
  }

  public function create($group, $code, $metadata)
  {
    try {
      $container_size_code = $this->getContainerSizeCode($group, $metadata);
      $content_file = $this->getContentFile($group, $code);
      list($web_filepath, $web_type) = $this->getWebFilepathAndType($content_file, $group, $code, $metadata);
      list($width, $height) = $this->createWebFile($content_file, $web_filepath, $metadata, $container_size_code);

      $image = new \Dipo\Model\Image($code, $width, $height, $web_type);
      $this->addMetadata($image, $metadata, $container_size_code);

      return $image;
    } catch (Exception $e) {
      throw $e->addDetails(array(
        'group' => $group->getCode(),
        'element' => $code
      ));
    }
  }

}
