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

if (isset($app['facebook_app_id'])) {
    $app["twig"]->addGlobal('facebookAppId', $app['facebook_app_id']);
}

$app->match('/', function () use ($app) {
  if (!isset($app['start_at'])) {
    return $app['twig']->render('index.html.twig');
  }

  $config_value = $app['start_at'];
  $key = key($config_value);
  $value = current($config_value);

  /* Check if start_at configuration value is an array with one element */
  if (!is_array($config_value) || count($value) !== 1) {
    return config_error($app, 'start_at', 'not-array');
  }

  switch ($key) {
    case 'container':
      $container = $app['portfolio']->getContainerByCode($value);
      if ($container === null) {
        return config_error($app, 'start_at', 'not-found');
      }
      break;
    case 'random':
      $random_containers = array();
      $random_container_types = (array)$value;
      foreach ($random_container_types as $type) {
        switch ($type) {
          case 'group':
            $random_containers += $app['portfolio']->getGroupsForRandomStart();
            break;
          case 'tag':
            $random_containers += $app['portfolio']->getTagsSorted();
            break;
        }
      }

      if (count($random_containers) == 0) {
        return config_error($app, 'start_at', 'no-random-candidates');
      }

      $container = $random_containers[array_rand($random_containers)];
      break;
  }
  return $app->redirect('/portfolio/' . $container->getCode() . '/' . $container->getFirstElement()->getCode());
});

/**
 * BROWSE PORTFOLIO
 */

$app->match('sidebar', function (Request $request) use ($app) {
  return $app['twig']->render('sidebar.html.twig', array(
    'portfolio_groups' => $app['portfolio']->getGroupsSorted(),
    'browsing_code' => $request->query->get('browsing_code', '')
  ));
});

$app->match('tagcloud', function (Request $request) use ($app) {
  $tags = $app['portfolio']->getTagsSorted();
  $maximum_element_count = array_reduce($tags, function ($max, $tag) { return max($tag->getElementCount(),  $max); }, 0);
  $minimum_element_count = array_reduce($tags, function ($min, $tag) { return min($tag->getElementCount(),  $min); }, $maximum_element_count);
  return $app['twig']->render('tagcloud.html.twig', array(
    'minimum_element_count' => $minimum_element_count,
    'maximum_element_count' => $maximum_element_count,
    'tags' => $tags,
    'browsing_code' => $request->query->get('browsing_code', '')
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
      'id' => $container->getElementId($element),
      'containerSizeCode' => $element->getContainerSizeCode(),
      'html' => $html,
      'link_areas' => $element->getLinkAreas(),
      'tags' => array()
    );

    if (!(isset($default_description) && $element->getDescription() === $default_description)) {
      $element_data['description'] = $element->getDescription();
    }

    foreach ($element->getTags() as $tag) {
      $element_data['tags'][] = array(
        'code' => $tag->getCode(),
        'name' => $tag->getName(),
        'firstElementId' => $tag->getElementId($tag->getFirstElement())
      );
    }

    $elements_data[] = $element_data;
  }

  $browser_data = array(
    'elements' => $elements_data
  );

  if (isset($default_description)) {
    $browser_data['description'] = $default_description;
  }

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
$app->get('/login', function (Request $request) use ($app) {
  /* TODO Replace with a form-based login. Can't reliably log out with http basic auth */
  $username = $request->server->get('PHP_AUTH_USER', false);
  $password = $request->server->get('PHP_AUTH_PW');

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
})->before($must_be_logged_in);

$app->get('/admin/bijwerken', function () use ($app) {
  /* Start a new updater or continue with a running one */
  // TODO Prevent concurrent runs (due to user interruption): use locking
  if (!$app['session']->has('updater')) {
    $updater = new \Dipo\Updater\Updater(
      $app['content_path'],
      $app['web_portfolio_path'],
      $app['container_sizes'],
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
})->before($must_be_logged_in);

$app->get('/admin/bijwerken/annuleren', function () use ($app) {
  $app['session']->remove('updater');
  return $app->redirect('/admin');
})->before($must_be_logged_in);
