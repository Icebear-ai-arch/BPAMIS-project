<?php
/**
 * Lightweight knowledge loader & retriever for chatbot.
 * - Loads JSON topics
 * - Chunks long text
 * - Scores relevance via keyword overlap & simple TF weighting
 */

const KB_FILE = __DIR__ . '/topics.json';
const CHUNK_SIZE = 750;          // target characters per chunk
const CHUNK_OVERLAP = 120;       // overlap to preserve context
const MIN_SCORE_THRESHOLD = 0.12; // minimum score to include
const MAX_RESULTS = 3;

function kb_load_topics(): array {
    if (!file_exists(KB_FILE)) return [];
    $raw = file_get_contents(KB_FILE);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function kb_chunk_text(string $text, int $size = CHUNK_SIZE, int $overlap = CHUNK_OVERLAP): array {
    $text = trim(preg_replace('/\s+/', ' ', $text));
    $len = strlen($text);
    if ($len <= $size) return [$text];
    $chunks = [];
    $start = 0;
    while ($start < $len) {
        $end = min($start + $size, $len);
        // try to break at sentence boundary
        if ($end < $len) {
            $periodPos = strrpos(substr($text, $start, $end - $start), '. ');
            if ($periodPos !== false && $periodPos > $size * 0.5) {
                $end = $start + $periodPos + 1; // include period
            }
        }
        $chunk = trim(substr($text, $start, $end - $start));
        if ($chunk !== '') $chunks[] = $chunk;
        if ($end >= $len) break;
        $start = max($end - $overlap, $start + 1);
    }
    return $chunks;
}

function kb_index(): array {
    $topics = kb_load_topics();
    $index = [];
    foreach ($topics as $topic) {
        $chunks = kb_chunk_text($topic['text']);
        foreach ($chunks as $i => $chunk) {
            $index[] = [
                'topic_id' => $topic['id'],
                'title' => $topic['title'],
                'source' => $topic['source'],
                'updated_at' => $topic['updated_at'],
                'pinned' => $topic['pinned'],
                'content' => $chunk,
            ];
        }
    }
    return $index;
}

function kb_score(string $query, string $text): float {
    $qTokens = kb_tokens($query);
    if (!$qTokens) return 0.0;
    $tTokens = kb_tokens($text);
    if (!$tTokens) return 0.0;
    $freq = array_count_values($tTokens);
    $score = 0.0;
    foreach ($qTokens as $qt) {
        if (isset($freq[$qt])) {
            // weight by sqrt frequency to diminish spam
            $score += 1.0 + sqrt($freq[$qt]);
        }
    }
    // normalize by log(length)
    $norm = max(1.0, log(5 + count($tTokens), 10));
    return $score / $norm;
}

function kb_tokens(string $text): array {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9₱]+/u', ' ', $text);
    $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $stop = ['the','a','an','is','are','to','of','and','or','at','by','on','for','in','with','was','were','be','been','must','can'];
    return array_values(array_diff($parts, $stop));
}

function kb_retrieve(string $query): array {
    $index = kb_index();
    $scored = [];
    foreach ($index as $row) {
        $score = kb_score($query, $row['content']);
        if ($row['pinned']) $score += 2.0; // strong boost for pinned topics
        if ($score >= MIN_SCORE_THRESHOLD) {
            $row['score'] = $score;
            $scored[] = $row;
        }
    }
    usort($scored, fn($a,$b)=> $b['score'] <=> $a['score']);
    return array_slice($scored, 0, MAX_RESULTS);
}

function kb_build_context(array $chunks): string {
    if (!$chunks) return '';
    $lines = ["Barangay Knowledge Base Context (top matches):"];
    foreach ($chunks as $c) {
        $lines[] = '- '.$c['title'].': '.$c['content'].' (Source: '.$c['source'].'; Updated: '.$c['updated_at'].')';
    }
    return implode("\n", $lines);
}

?>
