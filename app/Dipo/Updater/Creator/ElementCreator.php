<?php
namespace Dipo\Updater\Creator;

use Symfony\Component\Finder\Finder;
use Dipo\Updater\Exception;

abstract class ElementCreator
{

  protected $content_path;
  protected $web_path;
  protected $container_sizes;

  public function __construct($content_path, $web_path, $container_sizes)
  {
    $this->content_path = $content_path;
    $this->web_path = $web_path;
    $this->container_sizs = $container_sizes;
  }

  protected function getContentFile($group, $code)
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
        'action' => 'content',
        'error' => 'file-missing'
      ));

    $files->next();
    if ($files->valid()) {
      throw new Exception(array(
        'action' => 'content',
        'error' => 'multiple-files'
      ));
    }

    return $content_file;
  }

  protected function addMetadata($image, $metadata, $container_size_code)
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

  protected function getContainerSizeCode($group, $metadata)
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

  /**
  * Return the model-object for the element being created by this creator.
  */
  abstract protected function createElement($group, $code, $metadata, $content_file, $container_size_code);

  /**
  * Return a short lower-cased-string fot the type of elements that are being created
  */
  abstract protected function getElementTypeCode();

  public function create($group, $code, $metadata)
  {
    try {
      $container_size_code = $this->getContainerSizeCode($group, $metadata);
      $content_file = $this->getContentFile($group, $code);

      $element = $this->createElement($group, $code, $metadata, $content_file, $container_size_code);

      $this->addMetadata($element, $metadata, $container_size_code);

      return $element;
    } catch (Exception $e) {
      throw $e->addDetails(array(
        'type' => $this->getElementTypeCode(),
        'group' => $group->getCode(),
        'element' => $code
      ));
    }
  }
}
