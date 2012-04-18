<?php

/* Enable auto-loading the composer-managed dependencies */
require_once __DIR__.'/../vendor/.composer/autoload.php';

/* Set-up the Silex application */
$app = new Silex\Application();

/* Load the local configuration */
require_once __DIR__ . '/config.php';

/* Register the Twig Template Engine and use a global layout template */
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
    'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));

$app['portfolio'] = $app->share(function () {
  // Temporarily include hardcoded portfolio objects
  require_once __DIR__ . '/../portfolio-data.php';
  return $portfolio;
});

$app->before(function () use ($app) {
    $app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.html.twig'));
});

/* Load the controller definitions */
require_once __DIR__ . '/controllers.php';

/* Return the Silex Application (that is ready to run) */
return $app;
