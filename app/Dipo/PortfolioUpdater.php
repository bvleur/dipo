<?php
namespace Dipo;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Updates the portfolio:
 *  - Use the content and meta-data in the content path
 *  - Populate the portfolio web path with the content
 *  - Generate a matching Dipo\Model\Portfolio from the content
 *  - Write out a serialized version of this model in the web path
 *
 * Usage:
 *   To facillitate updating the portfolio in response to HTTP request from a
 *   browser without timing out, the intensive work can be processed in multiple calls.
 *
 *   For the first run you can construct a new PorfolioUpdater object, inject
 *   dependencies and call process(..).
 *
 *   For subsequent processing, you call process(..) with a number of seconds
 *   for the maximum processing time. This should be called at least once,
 *   until isDone() or an exception is thrown. To resume processing in a new
 *   request, you can serialize an unserialize the updater. After unserializing
 *   the dependencies must be re-injected (to reduce the serialized-size).
 *
 * Dependencies:
 *   Before calling process() an Imagine instance should be set using setImagine()
 */
class PortfolioUpdater
{
  /* configuration */
  private $content_path;
  private $web_path;
  private $imagine;

  /* processing public state */
  private $completed;
  private $total;
  private $is_done;
  private $portfolio;

  /* processing internal state */
  private $metadata = array();
  private $metadata_group_codes = array(); // Utillity look-up array for indices to group codes
  private $group;
  private $group_index;
  private $element_index;

  public function __construct($content_path, $web_path, $maximum_width, $maximum_height)
  {
    $this->content_path = $content_path;
    $this->web_path = $web_path;
    $this->maximum_width = $maximum_width;
    $this->maximum_height = $maximum_height;
  }

  public function setImagine(\Imagine\Image\ImagineInterface $imagine)
  {
    $this->imagine = $imagine;
  }

  private function __sleep()
  {
    unset($this->imagine);
  }

  public function isDone()
  {
    return $this->is_done;
  }

  public function getCompleted()
  {
    return $this->completed;
  }

  public function getTotal()
  {
    return $this->total;
  }

  private function scan()
  {
    /* Read all portfolio.yml files and keep the path-names */
    $this->total = 0; // Element count
    $finder = new Finder();
    $finder->in($this->content_path)->depth(1)->name('portfolio.yml');

    $yaml = new Yaml();
    foreach ($finder as $file) {
      try {
        $group_code = $file->getRelativePath();
        if (urlencode($group_code) != $group_code)
          throw new PortfolioUpdaterException(array(
            'action' => 'scan-folders',
            'error' => 'unsupported-characters',
            'path' => $group_code));

        $file_content = file_get_contents($file->getPathname());
        if ($file_content === false) {
          throw new PortfolioUpdaterException(array(
            'action' => 'scan-folders',
            'error' => 'cant-read-metadata-file',
            'path' => $group_code));
        }

        $group_metadata = $yaml->parse($file_content);

        $this->metadata[$file->getRelativePath()] = $group_metadata;

        $this->total += count($group_metadata['elements']);
      } catch (ParseException $e) {
        throw new PortfolioUpdaterException(array(
          'action' => 'scan-folders',
          'error' => 'cant-read-metadata-file',
          'path' => $group_code,
          'exception_message' => $e->getMessage()));
      }
    }

    // TODO Warn on depth > 1 folders and files
    // TODO Warn on folders without portfolio.yml
    // TODO Warn portfolio.yml in content root

  }

  /**
   *
   * @param $maximum_processing_time Time in seconds
   */
  public function process($maximum_processing_time)
  {
    $timeout_at = microtime(true) + ($maximum_processing_time);

    if (!isset($this->group_index)) {
      $this->scan();

      /* Initialize processing progress and result */
      $this->completed = 0;
      $this->is_done = false;
      $this->portfolio = new Model\Portfolio();
      $this->group_index = 0;
      $this->metadata_group_codes = array_keys($this->metadata);

      /* To ensure the caller can supply the end user with progress information quickly, return early */
      return;
    }

    /* Keep processing elements if we haven't reached the time-out and are not done yet */
    while ((microtime(true) < $timeout_at) && !$this->isDone()) {
      $group_code = $this->metadata_group_codes[$this->group_index];
      $group_metadata = $this->metadata[$group_code];

      /* Create group (if we are a the first element) */
      if (!isset($this->element_index)) {
        $this->group = $this->createGroup($group_code, $group_metadata);
        $this->portfolio->addGroup($this->group);
        $this->element_index = 0;
      }

      /* Process element */
      $group_element_codes = array_keys($group_metadata['elements']);
      $element_code = $group_element_codes[$this->element_index];
      $element_metadata = $group_metadata['elements'][$element_code];

      $element = $this->createImage($this->group, $element_code, $element_metadata);
      $this->group->addElement($element);

      $this->completed++;

      /* Continue with next element */
      if ($this->element_index + 1 < count($group_metadata['elements'])) {
        $this->element_index++;
      } else {
        /* This group is done, go to next group (if any) */
        unset($this->element_index);
        if ($this->group_index + 1 < count($this->metadata)) {
          $this->group_index++;
        } else {
          unset($this->group_index);
          unset($this->group);
          $this->finish();
        }
      }
    }
  }

  private function createGroup($code, $metadata)
  {
    try {
      $group = new Model\PortfolioGroup(
        $code,
        $this->metadataGet(true, $metadata, 'created-at', 'DateTime'),
        $this->metadataGet(true, $metadata, 'title')
      );
      $group->setDescription($this->metadataGet(true, $metadata, 'description'));
    } catch (PortfolioUpdaterException $pue) {
      throw $pue->addDetails(array(
        'action' => 'metadata-group',
        'group' => $code
      ));
    }

    return $group;
  }

  private function getMaximumBox()
  {
    return new \Imagine\Image\Box($this->maximum_width, $this->maximum_height);
  }

  private function createImage($group, $code, $metadata)
  {
    $filesystem = new Filesystem();

    $extension = 'jpg'; // TODO Support other file-types
    $content_filepath = $this->content_path . '/'. $group->getCode() . '/' . $code . '.' . $extension;

    if (!is_file($content_filepath))
      throw new PortfolioUpdaterException(array(
        'action' => 'content-image',
        'error' => 'file-missing',
        'group' => $group->getCode(),
        'element' => $code,
      ));

    $web_filepath = $this->web_path . '/portfolio-content/' . $group->getCode() . '/' . $code . '.' .$extension;

    try {
      $content_image = $this->imagine->open($content_filepath);
    } catch (\Imagine\Exception\InvalidArgumentException $e) {
      throw new PortfolioUpdaterException(array(
        'action' => 'content-image',
        'error' => 'open-error',
        'group' => $group->getCode(),
        'element' => $code,
        'exception_message' => $e->getMessage()
      ));
    }

    $size = $content_image->getSize();

    if ($this->getMaximumBox()->contains($size)) {
      /* No resizing needed. Copy the content */
      $filesystem->copy($content_filepath, $web_filepath, true);
    } else {
      /* The content file is bigger than the maximum size: resize it an save */
      $filesystem->mkdir(dirname($web_filepath));
      $resized_image = $content_image->thumbnail($this->getMaximumBox());
      $resized_image->save($web_filepath);
      $size = $resized_image->getSize();
    }

    $image = new Model\PortfolioImage($code, $size->getWidth(), $size->getHeight());

    try {
      $image->setDescription($this->metadataGet(false, $metadata, 'description'));
    } catch (PortfolioUpdaterException $pue) {
      throw $pue->addDetails(array(
        'action' => 'metadata-element',
        'group' => $group->getCode(),
        'element' => $code
      ));
    }

    return $image;
  }

  private function metadataGet($required, $data, $key, $type = 'string')
  {
    if ($required && !array_key_exists($key, $data)) {
      throw new PortfolioUpdaterException(array(
        'error' => 'missing',
        'key' => $key,
        'data_type' => $type
      ));
    }

    $value = $data[$key];

    switch ($type) {
    case 'string':
      // TODO Figure out non-empty case
      return $value;
    case 'DateTime':
      // TODO Figure out error handling.
      return new \DateTime($value);
    }
  }

  private function finish()
  {
    /* Write out the portfolio */
    file_put_contents(
      $this->web_path . '/portfolio-content/database.php',
      array(
        "<?php header('Location: ../');/*\n",
        base64_encode(serialize($this->portfolio))
      ),
      LOCK_EX
    );
    $this->is_done = true;
  }

}
