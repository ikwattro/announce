<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/', function() use($app) {
    return $app['twig']->render('index.html.twig');
})
    ->bind('welcome');

$app->get('/thankyou', function() use ($app) {
    return $app['twig']->render('success.html.twig');
});

$app->post('/register', function (Request $request) use ($app) {
    $email = $request->request->get('user_email');
    $loc = $request->getPreferredLanguage();
    $locs = $request->getLanguages();
    $locales = [];
    foreach ($locs as $k => $l) {
        $locales[] = ['code' => $l, 'priority' => $k];
    }
    $ip = $request->getClientIp();


    $query = 'MERGE (registration:Registration {time:{time}})
    MERGE (user:User {email:{email}})
    SET user.ip = {ip}
    MERGE (registration)-[:FROM_USER]->(user)
    WITH user
    UNWIND {languages} as l
    MERGE (lg:Language {code: l.code})
    MERGE (user)-[:BROWSER_LANGUAGE {priority: l.priority}]->(lg)
    WITH user
    MERGE (pl:Language {code: {pl}})
    MERGE (user)-[:PREFERED_LANGUAGE]->(pl)';
    $p = [
        'time' => (time() * 1000),
        'email' => trim((string) strtolower($email)),
        'ip' => $ip,
        'languages' => $locales,
        'pl' => $loc,
    ];

    try {
        $neo = $app['neo'];
        $neo->sendCypherQuery($query, $p);
    } catch (\Neoxygen\NeoClient\Exception\Neo4jException $e) {
        // ...
    }

    try {
        $msg = Swift_Message::newInstance();
        $msg->setSubject('New registration');
        $msg->setFrom(array('announce@octifyapp.com' => 'octify announce'));
        $msg->setTo(array('willemsen.christophe@gmail.com' => 'Christophe Willemsen'));
        $msg->setBody('New registration for Octify with email ' . $email);
        $transport = Swift_SmtpTransport::newInstance('smtp.gmail.com', 465, 'ssl')
          ->setUsername('octifylabz@gmail.com')
          ->setPassword('error!2101CWX');
        $mailer = Swift_Mailer::newInstance($transport);
        $mailer->send($msg);
    } catch (Swift_TransportException $e) {
        // ...
    }

    return $app->redirect('/thankyou');
})
->bind('register')
;

$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html.twig',
        'errors/'.substr($code, 0, 2).'x.html.twig',
        'errors/'.substr($code, 0, 1).'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
