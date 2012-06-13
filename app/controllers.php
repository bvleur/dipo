<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

function config_error($app, $variable, $error_code) {
  return $app['twig']->render('error.config.' . $variable . '.' . $error_code . '.html.twig',
    array(
      'variable_name' => $variable,
      'variable_value' => $app[$variable]
    )
  );
}

$app->match('/', function () use ($app) {
  if (isset($app['start_group_or_tag'])) {
    $container = $app['portfolio']->getContainerByCode($app['start_group_or_tag']);
    if ($container === null) {
      return config_error($app, 'start_group_or_tag', 'invalid');
    }
    return $app->redirect('/portfolio/' . $container->getCode() . '/' . $container->getFirstElement()->getCode());
  }
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

$app->get('/portfolio/{container}/browser-data', function ($container) use ($app) {
  if (!$container)
    $app->abort(404);

  /* Groups have a shared description, which compresses nicely into a default description field */
  if ($container instanceof \Dipo\Model\Group) {
    $default_description = $container->getDescription();
  }

  $elements_data = array();
  foreach ($container->getElements() as $element) {
    $html = $app['twig']->render('element.' . $element->getElementType() . '.html.twig', array(
      'element' => $element
    ));

    $element_data = array(
      'id' => $element->getCode(),
      'html' => $html,
      'tags' => array()
    );

    if (!(isset($default_description) && $element->getDescription() === $default_description)) {
      $element_data['description'] = $element->getDescription();
    }

    foreach ($element->getTags() as $tag) {
      $element_data['tags'][] = array('code' => $tag->getCode(), 'name' => $tag->getName());
    }

    $elements_data[] = $element_data;
  }

  $browser_data = array(
    'description' => $default_description,
    'elements' => $elements_data
  );

  return new Response(json_encode($browser_data));
})->convert('container', array($app['portfolio'], 'getContainerByCode'));

$app->get('/portfolio/{container}/{element}', function ($container, $element) use ($app) {
  if (!$container)
    $app->abort(404);

  $element = $container->getElement($element);
  if (!$element)
    $app->abort(404);

  /* The index from the URL is user-facing and thus 1-based */
  return $app['twig']->render('portfolio.html.twig', array(
    'browsing' => $container,
    'index' => $container->getIndexOfElement($element),
    'element' => $element
  ));
})->convert('container', array($app['portfolio'], 'getContainerByCode'));


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
  if (!$app['session']->has('updater')) {
    $updater = new \Dipo\Updater\Updater(
      $app['content_path'],
      $app['web_portfolio_path'],
      $app['maximum_width'],
      $app['maximum_height'],
      $app['updater.imagine_driver']
    );
    $app['session']->set('updater', $updater);
  } else {
    $updater = $app['session']->get('updater');
  }

  try {
    $updater->process($app['updater.processing_step_seconds']);
  } catch (\Dipo\Updater\Exception $e) {
    $failure = $e;
  }

  /* If the updater is done, we can forget */
  if ($updater->isDone() || isset($failure)) {
    $app['session']->remove('updater');
  }

  return $app['twig']->render('update.html.twig',
    array(
      'user' => $app['session']->get('user'),
      'total' => $updater->getTotal(),
      'completed' => $updater->getCompleted(),
      'is_done' => $updater->isDone(),
      'failure' => isset($failure) ? $failure->getDetails() : false
    ));
})->middleware($must_be_logged_in);

$app->get('/admin/bijwerken/annuleren', function () use ($app) {
  $app['session']->remove('updater');
  return $app->redirect('/admin');
})->middleware($must_be_logged_in);
