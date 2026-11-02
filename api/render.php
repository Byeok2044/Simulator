<?php
/** Read one complete page; parse display-only IF/EACH blocks without executing code. */
function renderPage(string $path, array $data): string {
    $source = file_get_contents($path);
    $tokens = preg_split('/(<!-- (?:IF|EACH|END) [a-z_]+ -->|\{\{\{?[a-z_]+\}\}\}?)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
    $index = 0;
    $nodes = parsePageNodes($tokens, $index);
    return renderPageNodes($nodes, $data);
}

function parsePageNodes(array $tokens, int &$index, ?string $end = null): array {
    $nodes = [];
    while ($index < count($tokens)) {
        $token = $tokens[$index++];
        if (preg_match('/^<!-- (IF|EACH|END) ([a-z_]+) -->$/', $token, $match)) {
            if ($match[1] === 'END') {
                if ($end !== $match[2]) throw new RuntimeException('Mismatched HTML display block.');
                return $nodes;
            }
            $nodes[] = ['type' => $match[1], 'key' => $match[2], 'children' => parsePageNodes($tokens, $index, $match[2])];
        } elseif (preg_match('/^\{\{(\{?)([a-z_]+)\}\}\}?$/', $token, $match)) {
            $nodes[] = ['type' => $match[1] === '{' ? 'raw' : 'value', 'key' => $match[2]];
        } else $nodes[] = ['type' => 'text', 'text' => $token];
    }
    if ($end !== null) throw new RuntimeException('Unclosed HTML display block.');
    return $nodes;
}

function renderPageNodes(array $nodes, array $data): string {
    $output = '';
    foreach ($nodes as $node) {
        if ($node['type'] === 'text') { $output .= $node['text']; continue; }
        $value = $data[$node['key']] ?? '';
        if ($node['type'] === 'IF') {
            if ($value) $output .= renderPageNodes($node['children'], $data);
        } elseif ($node['type'] === 'EACH') {
            foreach ($value ?: [] as $item) $output .= renderPageNodes($node['children'], array_replace($data, $item));
        } elseif ($node['type'] === 'raw') {
            // Only PHP-generated SVG is inserted as markup; user values are always escaped.
            if ($node['key'] !== 'chart') throw new RuntimeException('Unsupported raw HTML field.');
            $output .= $value;
        } else $output .= h($value);
    }
    return $output;
}
