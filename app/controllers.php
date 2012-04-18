<?php
require_once(__DIR__ . '/model.php');

$app->match('/', function () use ($app) {
  return $app['twig']->render('index.html.twig');
});

$app->match('sidebar', function () use ($app) {
  return $app['twig']->render('sidebar.html.twig', array(
    'portfolio_groups' => $app['portfolio']->getGroupsSorted()
  ));
});
