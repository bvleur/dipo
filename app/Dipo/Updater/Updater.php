<?php
namespace Dipo\Updater;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use dflydev\markdown\MarkdownParser;

/**
 * Updates the portfolio:
 *  - Use the content and meta-data in the content path
 *  - Populate the portfolio web path with the content
 *  - Generate a matching \Dipo\Model\Portfolio from the content
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
 *   request, you can serialize an unserialize the updater.
 */
class Updater
{
  /* configuration */
  private $content_path;
  private $web_path;

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

  /* model creator */
  private $image_creator;

  public function __construct($content_path, $web_path, $maximum_width, $maximum_height, $imagine_driver)
  {
    $this->content_path = $content_path;
    $this->web_path = $web_path;
    $this->image_creator = new ImageCreator($content_path, $web_path, $maximum_width, $maximum_height, $imagine_driver);
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
          throw new Exception(array(
            'action' => 'scan-folders',
            'error' => 'unsupported-characters',
            'path' => $group_code));

        $file_content = file_get_contents($file->getPathname());
        if ($file_content === false) {
          throw new Exception(array(
            'action' => 'scan-folders',
            'error' => 'cant-read-metadata-file',
            'path' => $group_code));
        }

        $group_metadata = $yaml->parse($file_content);

        $this->metadata[$file->getRelativePath()] = $group_metadata;

        $this->total += count($group_metadata['elements']);
      } catch (ParseException $e) {
        throw new Exception(array(
          'action' => 'scan-folders',
          'error' => 'cant-parse-metadata-file',
          'group' => $group_code,
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
      $this->portfolio = new \Dipo\Model\Portfolio();
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

      $element = $this->image_creator->create($this->group, $element_code, (array)$element_metadata);
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
      $group = new \Dipo\Model\Group(
        $code,
        self::metadataGet(true, $metadata, 'created-at', 'DateTime'),
        self::metadataGet(true, $metadata, 'title'),
        self::metadataGet(false, $metadata, 'set', 'integer', 1)
      );
      $group->setDescription(self::metadataGet(true, $metadata, 'description', 'markdown-as-html'));
    } catch (Exception $e) {
      throw $e->addDetails(array(
        'action' => 'metadata-group',
        'group' => $code
      ));
    }

    return $group;
  }

  public static function metadataGet($required, $data, $key, $type = 'string', $default = null)
  {
    if (!array_key_exists($key, $data)) {
      if ($required) {
        throw new Exception(array(
          'error' => 'missing',
          'key' => $key,
          'data_type' => $type
        ));
      } else {
        return $default;
      }
    }

    $value = $data[$key];

    switch ($type) {
    case 'string':
      // TODO Figure out non-empty case
      return $value;
    case 'integer':
      // TODO Figure out error handling.
      return (integer)$value;
    case 'DateTime':
      // TODO Figure out error handling.
      return new \DateTime($value);
    case 'markdown-as-html':
      // TODO Figure out error handling.
      $markdownParser = new \dflydev\markdown\MarkdownParser();
      return $markdownParser->transformMarkdown($value);
    }
  }

  private function finish()
  {
    /* Write out the portfolio */
    file_put_contents(
      $this->web_path . '/database.php',
      array(
        "<?php header('Location: ../');/*\n",
        base64_encode(serialize($this->portfolio))
      ),
      LOCK_EX
    );
    $this->is_done = true;
  }

}
