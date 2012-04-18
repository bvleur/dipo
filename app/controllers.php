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

$app->get('/portfolio/{group}/{element}', function ($group, $element) use ($app) {
  if (!$group)
    $app->abort(404);

  $element = $group->getElement($element);
  if (!$element)
    $app->abort(404);

  /* The index from the URL is user-facing and thus 1-based */
  return $app['twig']->render('portfolio.html.twig', array(
    'browsing' => $group,
    'element' => $element
  ));
})->convert('group', array($app['portfolio'], 'getGroupByCode'));
