<?php

use Alfred\Workflows\Workflow;

use AlgoliaSearch\Client as Algolia;
use AlgoliaSearch\Version as AlgoliaUserAgent;

require __DIR__ . '/vendor/autoload.php';

$query = $argv[1];
$branch = empty($_ENV['branch']) ? 'master' : $_ENV['branch'];

$workflow = new Workflow;
$parsedown = new Parsedown;
$algolia = new Algolia('8BB87I11DE', '8e1d446d61fce359f69cd7c8b86a50de');

AlgoliaUserAgent::addSuffixUserAgentSegment('Alfred Workflow', '0.2.1');

$index = $algolia->initIndex('docs');
$search = $index->search($query, ['tagFilters' => $branch]);
$results = $search['hits'];

if (empty($results)) {
    $workflow->result()
        ->title('No matches')
        ->icon('google.png')
        ->subtitle("No match found in the {$branch} docs. Search Google for: \"{$query}\"")
        ->arg("https://www.google.com/search?q=laravel+{$query}")
        ->quicklookurl("https://www.google.com/search?q=laravel+{$query}")
        ->valid(true);

    echo $workflow->output();
    exit;
}

foreach ($results as $hit) {
    $hasText = isset($hit['_highlightResult']['content']['value']);
    $hasSubtitle = isset($hit['h2']);

    $title = $hit['h1'];
    $subtitle = $hasSubtitle ? $hit['h2'] : null;

    if ($hasText) {
        $subtitle = $hit['_highlightResult']['content']['value'];

        if ($hasSubtitle) {
            $title = "{$title} » {$hit['h2']}";
        }
    }

    $title = strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

    $subtitle = $parsedown->line($subtitle);
    $subtitle = strip_tags(html_entity_decode($subtitle, ENT_QUOTES, 'UTF-8'));

    $workflow->result()
        ->uid($hit['objectID'])
        ->title($title)
        ->autocomplete($title)
        ->subtitle($subtitle)
        ->arg("https://laravel.com/docs/{$branch}/{$hit['link']}")
        ->quicklookurl("https://laravel.com/docs/{$branch}/{$hit['link']}")
        ->valid(true);
}

echo $workflow->output();
