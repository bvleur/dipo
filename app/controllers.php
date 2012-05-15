<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->match('/', function () use ($app) {
  return $app['twig']->render('index.html.twig');
});

/**
 * BROWSE PORTFOLIO
 */

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
    'index' => $group->getIndexOfElement($element),
    'element' => $element
  ));
})->convert('group', array($app['portfolio'], 'getGroupByCode'));


/**
 * ADMIN PORTFOLIO
 */
$app->get('/login', function () use ($app) {
  /* TODO Replace with a form-based login. Can't reliably log out with http basic auth */
  $username = $app['request']->server->get('PHP_AUTH_USER', false);
  $password = $app['request']->server->get('PHP_AUTH_PW');

  /* Find the user by username (and assign to $user if found) */
  foreach ($app['users'] as $u)
    if ($u['username'] = $username)
      $user = $u;

  /* Verify the entered password (if a user is found) */
  if (isset($user) && $user['password_hash'] === crypt($password, $user['password_hash'])) {
    /* Password is valid. Set user in session en return to the admin homepage */
    $app['session']->set('user', $user);
    return $app->redirect('/admin');
  }

  /* No valid username and password have been provided. Reqeust them */
  $response = new Response();
  $response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', 'DiPo admin'));
  $response->setStatusCode(401, 'Log in');
  return $response;
});

/* Utillity function that redirects users that are not logged in  */
$must_be_logged_in = function (Request $request) use ($app) {
  if (!$app['session']->has('user')) {
    return $app->redirect('/login');
  }
  return null;
};

$app->get('/admin', function () use ($app) {
  $user = $app['session']->get('user');
  return $app['twig']->render('admin.html.twig', array(
    'user' => $user
  ));
})->middleware($must_be_logged_in);

$app->get('/admin/bijwerken', function () use ($app) {
  /* Start a new updater or continue with a running one */
  // TODO Prevent concurrent runs (due to user interruption): use locking
  if (!$app['session']->has('portfolio_updater')) {
    $portfolio_updater = new Dipo\PortfolioUpdater(
      $app['content_path'],
      $app['web_path'],
      $app['maximum_width'],
      $app['maximum_height']
    );
    $app['session']->set('portfolio_updater', $portfolio_updater);
    $portfolio_updater->setImagine($app['imagine']);
    $portfolio_updater->start();
  } else {
    $portfolio_updater = $app['session']->get('portfolio_updater');
    $portfolio_updater->setImagine($app['imagine']);
    $portfolio_updater->process(30); // 30 seconds
  }

  /* If the portfolio updater is done, we can forget */
  if ($portfolio_updater->isDone())
    $app['session']->remove('portfolio_updater');

  return $app['twig']->render('update.html.twig', array(
    'user' => $app['session']->get('user'),
    'total' => $portfolio_updater->getTotal(),
    'completed' => $portfolio_updater->getCompleted(),
    'done' => $portfolio_updater->isDone()
  ));
})->middleware($must_be_logged_in);

$app->get('/admin/bijwerken/annuleren', function () use ($app) {
  $app['session']->remove('portfolio_updater');
  return $app->redirect('/admin');
})->middleware($must_be_logged_in);
