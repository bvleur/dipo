<?php
namespace Dipo\Updater\Creator;

use Symfony\Component\Filesystem\Filesystem;
use Dipo\Updater\Exception;

class HTMLCreator extends ElementCreator
{

  public function __construct($content_path, $web_path, $container_sizes)
  {
    parent::__construct($content_path, $web_path, $container_sizes);
  }

  protected function getElementTypeCode()
  {
    return 'html';
  }

  protected function createElement($group, $code, $metadata, $content_file, $container_size_code)
  {
    return new \Dipo\Model\HTML(
        $code,
        $metadata->getInteger('width', 0),
        $metadata->getInteger('height', 0),
        file_get_contents($content_file)
        );
  }

}
