<?php
use Deskpro\API\GraphQL;
require(__DIR__ . '/../vendor/autoload.php');

$client = new GraphQL\GraphQLClient('http://deskpro-dev.com');

$query = $client->createQuery('GetNews', [
    '$articleId' => 'ID!'
])->field('content_get_articles', 'id: $articleId', [
    'title',
    'content',
    'categories' => [
        'title'
    ]
]);

$data = $query->execute([
    'articleId' => 100
]);
print_r($data);