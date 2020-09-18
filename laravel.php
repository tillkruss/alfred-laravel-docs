<?php

use Alfred\Workflows\Workflow;

use Algolia\AlgoliaSearch\SearchClient;
use Algolia\AlgoliaSearch\Support\UserAgent;

require __DIR__ . '/vendor/autoload.php';

$query = $argv[1];

preg_match('/^\h*?v?(master|(?:[\d]+)(?:\.[\d]+)?(?:\.[\d]+)?)?\h*?(.*?)$/', $query, $matches);

if (! empty(trim($matches[1]))) {
    $branch = $matches[1];
    $query = $matches[2];
} else {
    $branch = getenv('branch');
}

if ($branch === 'latest') {
    $branch = null;
}

$subtext = getenv('alfred_theme_subtext');

if (empty($subtext)) {
    $subtext = '0';
}

$workflow = new Workflow;
$parsedown = new Parsedown;

$algolia = SearchClient::create('BH4D9OD16A', '7dc4fe97e150304d1bf34f5043f178c4');

UserAgent::addCustomUserAgent('Alfred Workflow', '0.3.0');

$index = $algolia->initIndex('laravel');
$search = $index->search($query, ['facetFilters' => [
    sprintf('version:%s', $branch ?: 'master'),
]]);

$results = $search['hits'];
$subtextSupported = $subtext === '0' || $subtext === '2';

if (empty($results)) {
    $google = sprintf('https://www.google.com/search?q=%s', rawurlencode("laravel {$query}"));

    $workflow->result()
        ->title($subtextSupported ? 'Search Google' : 'No match found. Search Google...')
        ->icon('google.png')
        ->subtitle(sprintf('No match found. Search Google for: "%s"', $query))
        ->arg($google)
        ->quicklookurl($google)
        ->valid(true);

    $workflow->result()
        ->title($subtextSupported ? 'Open Docs' : 'No match found. Open docs...')
        ->icon('icon.png')
        ->subtitle('No match found. Open laravel.com/docs...')
        ->arg('https://laravel.com/docs/')
        ->quicklookurl('https://laravel.com/docs/')
        ->valid(true);

    echo $workflow->output();
    exit;
}

$docs = sprintf('https://laravel.com/docs/%s', $branch ? $branch . '/' : '');
$urls = [];

foreach ($results as $hit) {
    $url = $docs . $hit['link'];

    if (in_array($url, $urls)) {
        continue;
    }

    $urls[] = $url;

    $hasText = isset($hit['_highlightResult']['content']['value']);

    $title = $hit['h1'];
    $subtitle = subtitle($hit);

    if (! $subtextSupported && $subtitle) {
        $title = "{$title} » {$subtitle}";
    }

    if ($subtextSupported) {
        $text = $subtitle;

        if ($hasText) {
            $text = $hit['_highlightResult']['content']['value'];

            if ($subtitle) {
                $title = "{$title} » {$subtitle}";
            }
        }
    }

    $title = strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

    $text = $parsedown->line($text);
    $text = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));

    $workflow->result()
        ->uid($hit['objectID'])
        ->title($title)
        ->autocomplete($title)
        ->subtitle($text)
        ->arg($url)
        ->quicklookurl($url)
        ->valid(true);
}

echo $workflow->output();

function subtitle($hit)
{
    if (isset($hit['h4'])) {
        return $hit['h4'];
    }

    if (isset($hit['h3'])) {
        return $hit['h3'];
    }

    if (isset($hit['h2'])) {
        return $hit['h2'];
    }

    return null;
}
