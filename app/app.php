<?php
/* Enable auto-loading the composer-managed dependencies */
require_once __DIR__.'/../vendor/.composer/autoload.php';

/* Load Silex */
require_once __DIR__.'/../silex.phar';

/* Load the local configuration */
require_once __DIR__ . '/config.php';

/* Set-up the Silex application */
$app = new Silex\Application();

/* Register the Twig Template Engine and use a global layout template */
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
    'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));

$app->before(function () use ($app) {
    $app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.twig'));
});

/* Load the controller definitions */
require_once __DIR__ . '/controllers.php';

/* Return the Silex Application (that is ready to run) */
return $app;
