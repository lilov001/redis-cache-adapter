<?php

require 'vendor/autoload.php';

$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);

$container = $app->getContainer();

$container['config'] = function () {
    return new Noodlehaus\Config([
        __DIR__ . '/config/cache.php',
        __DIR__ . '/config/db.php',
        __DIR__ . '/config/hn.php',
    ]);
};

$container['db'] = function ($c) {
    return new PDO(
        'mysql:host=' . $c->config->get('db.host') . ';dbname=' . $c->config->get('db.db_name'),
        $c->config->get('db.name'),
        $c->config->get('db.pass')
    );
};

$container['http'] = function () {
    return new \GuzzleHttp\Client;
};

$container['cache'] = function ($c) {
    $client = new \Predis\Client([
        'scheme' => 'tcp',
        'host' => $c->config->get('cache.connections.redis.host'),
        'port' => $c->config->get('cache.connections.redis.port'),
        'password' => $c->config->get('cache.connections.redis.password')
    ]);

    return new \App\Cache\RedisAdapter($client);
};

$app->get('/users', function ($request, $response) {
    $users = $this->cache->remember('users', 10, function () {
        return json_encode($this->db->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC));
    });

    return $response->withHeader('Content-Type', 'application/json')->write($users);
});

$app->get('/hn', function ($request, $response) {
    $stories = $this->cache->remember('hn:top-stories', 10, function () {
        $stories = [];
        $res = $this->http->request('GET', $this->config->get('hn.domain') . '/topstories.json');

        foreach (array_slice(json_decode($res->getBody()), 0, 15) as $storyId) {
            $res = $this->http->request('GET', $this->config->get('hn.domain') . '/item/' . $storyId . '.json');
            $stories[] = json_decode($res->getBody());
        }

        return json_encode($stories);
    });

    return $response->withHeader('Content-Type', 'application/json')->write($stories);
});

$app->run();
