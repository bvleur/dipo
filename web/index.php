<?php
require_once __DIR__.'/../vendor/.composer/autoload.php';
require_once __DIR__.'/../silex.phar';

$app = new Silex\Application();

/* Only enable following lines while debugging */
$app['debug'] = true;
ini_set('display_errors', 1);

/*
$app->get('/hello/{name}', function($name) use($app) {
    return 'Hello '.$app->escape($name);
});
 */

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
    'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));

$app->before(function () use ($app) {
    $app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.twig'));
});

$app->match('/', function () use ($app) {
    return $app['twig']->render('index.twig');
});

$app->run();
