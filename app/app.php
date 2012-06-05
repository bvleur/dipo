<?php

/* Enable auto-loading the composer-managed dependencies */
require_once __DIR__.'/../vendor/autoload.php';

/* Set-up the Silex application */
$app = new Silex\Application();

/* Load the local configuration */
require_once __DIR__ . '/config.defaults.php';
require_once __DIR__ . '/config.php';

/* Register the Twig Template Engine and use a global layout template */
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views'
));

/* Register the (shared) imagine service */
$app['imagine'] = $app->share(function ($app) {
  $class = sprintf('\Imagine\%s\Imagine', $app['imagine.driver']);
  return new $class();
});

$app['portfolio'] = $app->share(function ($app) {
  $database_file = file($app['web_path'] . '/portfolio-content/database.php');
  // TODO Handle errors (missing database, damaged file)
  $portfolio = unserialize(base64_decode($database_file[1]));
  return $portfolio;
});

$app->before(function () use ($app) {
    $app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.html.twig'));
});

/* Register and start the Session Service */
$app->register(new Silex\Provider\SessionServiceProvider());
$app['session']->start();

/* Load the controller definitions */
require_once __DIR__ . '/controllers.php';

/* Return the Silex Application (that is ready to run) */
return $app;
