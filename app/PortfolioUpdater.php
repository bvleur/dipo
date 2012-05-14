<?php
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class PortfolioUpdater
{
  /* configuration */
  private $content_path;

  /* processing public state */
  private $fail_reason;
  private $completed;
  private $total;
  private $is_done;

  /* processing internal state */
  private $metadata = array();
  private $group;
  private $group_index;
  private $element_index;

  public function __construct($app)
  {
    /* We don't keep the $app instance (DI Container) in a member variable 
     * because we don't want to serialize the entire container.
     *
     * So we just keep what we need.
     */
    $this->content_path = $app['content_path'];

    $this->start();
  }

  public function hasFailed()
  {
    return isset($this->fail_reason);
  }

  public function getFailReason()
  {
    return $this->fail_reason;
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

  private function start()
  {
    /* Read all portfolio.yml files and keep the path-names */
    $this->total = 0; // Element count
    $finder = new Finder();
    $finder->in($this->content_path)->name('portfolio.yml');

    $yaml = new Yaml();
    foreach ($finder as $file) {
      try {
        $file_content = file_get_contents($file->getPathname());
        if ($file_content === false) {
          return $this->fail('Could not read portfolio.yml in content folder ' . $file->getRelativePath());
        }

        $group_metadata = $yaml->parse($file_content);

        $this->metadata[$file->getRelativePath()] = $group_metadata;

        $this->total += count($group_metadata['elements']);
      } catch (ParseException $e) {
        return $this->fail('Malformatted portfolio.yml in content folder ' . $file->getRelativePath() . ":\n" . $e->getMessage());
      }
    }

    $this->completed = 0;
    $this->is_done = false;
    $this->portfolio = new Portfolio();
  }

  /**
   *
   * @param $maximum_processing_time Time in seconds
   */
  public function process($maximum_processing_time)
  {
    $timeout_at = microtime(true) + ($maximum_processing_time * 1000);

    /* Utillity look-up array for indices to group codes */
    $metadata_group_codes = array_keys($this->metadata);

    /* If processing was paused before we can continue at the element that was
     * marked to be processed. Otherwise intialize the state at the first element */
    if (!isset($this->_group_index)) {
      $this->group_index = 0;
      $this->element_index = 0;
    }

    /* Keep processing elements if we haven't reached the time-out and are not done yet */
    while ((microtime(true) < $timeout_at) && !$this->isDone()) {
      $group_code = $metadata_group_codes[$this->group_index];
      $group_metadata = $this->metadata[$group_code];

      /* Create group (if we are a the first element) */
      if ($this->element_index == 0) {
        $this->group = $this->createGroup($group_code, $group_metadata);
        $this->portfolio->addGroup($this->group);
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
        if ($this->group_index + 1 < count($this->metadata)) {
          $this->group_index++;
          $this->element_index = 0;
        } else {
          $this->is_done = true;
        }
      }
    }
  }

  private function createGroup($code, $metadata)
  {
    $group = new PortfolioGroup(
      $code,
      $this->metadataGet(true, $metadata, 'created-at', 'DateTime'),
      $this->metadataGet(true, $metadata, 'title')
    );
    $group->setDescription($this->metadataGet(true, $metadata, 'description'));

    return $group;
  }

  private function createImage($group, $code, $metadata)
  {
    /* TODO Actually process JPEG */
    $width = 500;
    $height = 600;

    $image = new PortfolioImage($code, $width, $height);
    $image->setDescription($this->metadataGet(false, $metadata, 'description'));

    return $image;
  }

  private function metadataGet($required, $data, $key, $type = 'string')
  {
    if ($required && !array_key_exists($key, $data)) {
      $this->fail('Missing required "' . $key . '"');
      return null;
    }

    $value = $data[$key];

    switch ($type) {
    case 'string':
      // TODO Figure out non-empty case
      return $value;
    case 'DateTime':
      // TODO Figure out error handling.
      return new DateTime($value);
    }
  }

  private function fail($reason)
  {
    $this->fail_reason = $reason;
  }

}
