<?php

$app['content_path'] = realpath(__DIR__ . '/../content/');
$app['web_path'] = realpath(__DIR__ . '/../web/');

$app['imagine.driver'] = 'Gd'; // or Imagick or Gmagick
