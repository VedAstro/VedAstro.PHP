<?php

declare(strict_types=1);

/**
 * DEMO: Semantic search of the classical texts (RAG).
 *
 * USE CASE: ask a plain-English question and get the most relevant passages from works such as
 * Brihat Parashara Hora Shastra and Phaladeepika, with no exact keywords needed
 * DIFFICULTY: Intermediate
 *
 * This is the same retrieval step that powers VedAstro's own AI features. Feed the returned
 * passages into an LLM prompt to build a cited astrology chatbot.
 *
 * RUN: php examples/rag_vedic_books.php
 */

require_once __DIR__ . '/support.php';

use VedAstro\Generated\SearchSourceTextOptions;
use VedAstro\Response;

$client = demoClient();

// Which classical texts are searchable?
$texts = $client->calculate()->getAvailableSourceTexts();
demoHeading('Searchable texts');
foreach (Response::list($texts) as $title) {
    echo '  - ', $title, PHP_EOL;
}

demoPace();

// A natural-language question, not a keyword search.
demoHeading('Most relevant passages');
$passages = $client->calculate()->searchSourceText('effects of Saturn in the 7th house');

foreach (Response::list($passages) as $passage) {
    $score = Response::float($passage, 'score', 1.0);
    // A lower score is a closer match, so invert it into a relevance percentage.
    printf(
        "%s p.%s (%.0f%%)%s",
        Response::string($passage, 'sourceName', '-'),
        (string) Response::get($passage, 'pageNumber', '?'),
        (1 - $score) * 100,
        PHP_EOL
    );
    echo '   ', demoTruncate(Response::string($passage, 'text'), 200), '...', PHP_EOL, PHP_EOL;
}

demoPace();

// Narrow to one book and tune the knobs. A null property is omitted, so the server default
// applies to anything left unset.
$options = new SearchSourceTextOptions();
$options->topK = 3;
$options->sourceName = 'Hindu-Predictive-Astrology';
$options->contextSize = 800;

$focused = $client->calculate()->searchSourceText('results of Jupiter aspecting the Moon', $options);
echo 'Focused search returned ', Response::count($focused), ' passage(s).', PHP_EOL;

// BUILDING A CITED CHATBOT
// 1. $client->calculate()->searchSourceText($question)
// 2. number each hit and keep its book and page
// 3. ask the model to answer using only those passages, citing [1], [2], ...
