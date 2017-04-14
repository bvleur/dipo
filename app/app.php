<?php

/* Enable auto-loading the composer-managed dependencies */
require_once __DIR__.'/../vendor/autoload.php';

/* Set-up the Silex application */
$app = new Silex\Application();

/* Load the local configuration */
require_once __DIR__ . '/config.defaults.php';
$custom_config_path = __DIR__ . '/../custom/config.php';
if (file_exists($custom_config_path)) {
    include_once $custom_config_path;
}

/* Register the Twig Template Engine and use a global layout template */
$views_paths = array(__DIR__ . '/views');
$custom_views_path = __DIR__ . '/../custom/views';
if (is_dir($custom_views_path)) {
    array_unshift($views_paths, $custom_views_path);
}

$app->register(new Silex\Provider\HttpFragmentServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array('twig.path' => $views_paths));

$app['portfolio'] = $app->share(function ($app) {
  $database_path = $app['web_portfolio_path'] . '/database.php';
  if (!file_exists($database_path)) {
      return  new \Dipo\Model\Portfolio();
  }
  $database_file = file($database_path);
  // TODO Handle errors (damaged file)
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
