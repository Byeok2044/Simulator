<?php
// Fixed asset route also works behind the existing Vercel catch-all rewrite.
if (($_GET['asset'] ?? null) === 'styles') {
    header('Content-Type: text/css; charset=UTF-8');
    readfile(__DIR__ . '/assets/styles.css');
    exit;
}
require __DIR__ . '/controller.php';
require __DIR__ . '/view.php';
