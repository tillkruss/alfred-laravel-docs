<?php

use Alfred\Workflows\Workflow;

use AlgoliaSearch\Client as Algolia;
use AlgoliaSearch\Version as AlgoliaUserAgent;

require __DIR__ . '/vendor/autoload.php';

$query = $argv[1];
$branch = empty($_ENV['branch']) ? 'master' : $_ENV['branch'];
$subtext = empty($_ENV['alfred_theme_subtext']) ? '0' : $_ENV['alfred_theme_subtext'];

$workflow = new Workflow;
$parsedown = new Parsedown;
$algolia = new Algolia('8BB87I11DE', '8e1d446d61fce359f69cd7c8b86a50de');

AlgoliaUserAgent::addSuffixUserAgentSegment('Alfred Workflow', '0.2.1');

$index = $algolia->initIndex('docs');
$search = $index->search($query, ['tagFilters' => $branch]);
$results = $search['hits'];

$subtextSupported = $subtext === '0' || $subtext === '2';

if (empty($results)) {
    $fallback = sprintf('https://www.google.com/search?q=%s', rawurlencode("laravel {$query}"));

    $workflow->result()
        ->title(
            $subtextSupported ? 'No matches' : 'No match found. Search Google...'
        )
        ->icon('google.png')
        ->subtitle(
            sprintf('No match found in the %s docs. Search Google for: "Laravel %s"', $branch, $query)
        )
        ->arg($fallback)
        ->quicklookurl($fallback)
        ->valid(true);

    echo $workflow->output();
    exit;
}

$urls = [];

foreach ($results as $hit) {
    $url = sprintf("https://laravel.com/docs/%s/%s", $branch, $hit['link']);

    if (in_array($url, $urls)) {
        continue;
    }

    $urls[] = $url;

    $hasText = isset($hit['_highlightResult']['content']['value']);
    $hasSubtitle = isset($hit['h2']);

    $title = $hit['h1'];
    $subtitle = $hasSubtitle ? $hit['h2'] : null;

    if ($subtextSupported && $hasText) {
        $subtitle = $hit['_highlightResult']['content']['value'];

        if ($hasSubtitle) {
            $title = "{$title} » {$hit['h2']}";
        }
    }

    if (! $subtextSupported && $hasSubtitle) {
        $title = "{$title} » {$hit['h2']}";
    }

    $title = strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

    $subtitle = $parsedown->line($subtitle);
    $subtitle = strip_tags(html_entity_decode($subtitle, ENT_QUOTES, 'UTF-8'));

    $workflow->result()
        ->uid($hit['objectID'])
        ->title($title)
        ->autocomplete($title)
        ->subtitle($subtitle)
        ->arg($url)
        ->quicklookurl($url)
        ->valid(true);
}

echo $workflow->output();
