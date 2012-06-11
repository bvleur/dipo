<?php

$app['content_path'] = realpath(__DIR__ . '/../content/');
$app['web_path'] = realpath(__DIR__ . '/../web/');

$app['maximum_width'] = 700;
$app['maximum_height'] = 600;

$app['imagine.driver'] = 'Imagick'; // or Gmagick. Imagine also supports "Gd", but that comes with some limitations (extreme memory usage, no TIFF support)
$app['updater.processing_step_seconds'] = 10;

$app['title'] = 'My Digital Portfolio';
