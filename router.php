<?php
// Sperrt direkten HTTP-Zugriff auf .json-Dateien (Datenschutz).
// Verwendung: php -S localhost:8000 router.php
if (preg_match('/\.json$/i', $_SERVER['REQUEST_URI'])) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden';
    exit;
}
// Alle anderen Anfragen normal weiterleiten (statische Dateien + PHP)
return false;
