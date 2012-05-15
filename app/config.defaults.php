<?php

$app['content_path'] = realpath(__DIR__ . '/../content/');
$app['web_path'] = realpath(__DIR__ . '/../web/');

$app['maximum_width'] = 500;
$app['maximum_height'] = 600;

$app['imagine.driver'] = 'Gd'; // or Imagick or Gmagick
$app['updater.processing_step_seconds'] = 10;
