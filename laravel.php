<?php

use Alfred\Workflows\Workflow;

use Algolia\AlgoliaSearch\SearchClient;
use Algolia\AlgoliaSearch\Support\UserAgent;

require __DIR__ . '/vendor/autoload.php';

$query = $argv[1];

preg_match('/^\h*?v?(master|(?:[\d]+)(?:\.[\d]+)?(?:\.[\dx]+)?)?\h*?(.*?)$/', $query, $matches);

if (! empty(trim($matches[1]))) {
    $branch = $matches[1];
    $query = trim($matches[2]);
} else {
    $branch = getenv('branch');
}

if ($branch === 'latest') {
    $branch = null;
} elseif (is_numeric($branch) && $branch > 5) {
    // Format branch as docs URLs expect with .x suffix
    $branch = "{$branch}.x";
}

$subtext = getenv('alfred_theme_subtext');

if (empty($subtext)) {
    $subtext = '0';
}

$workflow = new Workflow;
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

$urls = [];

foreach ($results as $hit) {
    $url = $hit['url'];

    if (in_array($url, $urls)) {
        continue;
    }

    $urls[] = $url;

    $hasText = isset($hit['_highlightResult']['content']['value']);

    $title = $hit['hierarchy']['lvl0'];
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
        } else {
          $text = sprintf('%s » %s', $hit['hierarchy']['lvl1'], $hit['hierarchy']['lvl2']);
        }
    }

    $title = strip_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));

    $text = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    $text = preg_replace('/\s+/', ' ', $text);

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
    if (isset($hit['hierarchy']['lvl3'])) {
        return $hit['hierarchy']['lvl3'];
    }

    if (isset($hit['hierarchy']['lvl2'])) {
        return $hit['hierarchy']['lvl2'];
    }

    if (isset($hit['hierarchy']['lvl1'])) {
        return $hit['hierarchy']['lvl1'];
    }

    return null;
}
