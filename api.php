<?php
declare(strict_types=1);

// === Nagłówki / CORS ===
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight CORS — przeglądarka wysyła OPTIONS przed POST z application/json
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

const DIGITS   = '0123456789';
const SPECIALS = '!@#$%^&*()-_=+[]{};:,.?/|';
const MIN_WORDS_IN_LINE    = 5;
const MIN_CHARS_IN_LINE    = 24;
const MAX_PASSWORD_ATTEMPTS = 200;

const ICAO_PL = [
    'a' => 'Alicja',   'b' => 'Barbara',   'c' => 'Celina',   'd' => 'Danuta',
    'e' => 'Ewa',      'f' => 'Franciszka', 'g' => 'Grażyna',  'h' => 'Halina',
    'i' => 'Irena',    'j' => 'Jadwiga',   'k' => 'Krystyna', 'l' => 'Lucyna',
    'm' => 'Maria',    'n' => 'Natalia',   'o' => 'Olga',     'p' => 'Patrycja',
    'q' => 'Quentina', 'r' => 'Renata',    's' => 'Sabina',   't' => 'Teresa',
    'u' => 'Urszula',  'v' => 'Violetta',  'w' => 'Wanda',    'x' => 'Xenia',
    'y' => 'Yvona',    'z' => 'Zuzanna',
];

const DIGIT_NAMES = [
    '0' => 'zero',    '1' => 'jeden',   '2' => 'dwa',     '3' => 'trzy',
    '4' => 'cztery',  '5' => 'pięć',    '6' => 'sześć',   '7' => 'siedem',
    '8' => 'osiem',   '9' => 'dziewięć',
];

const VARIANTS = [
    ['key' => 'ascii',       'min' => 28, 'max' => 36, 'strip' => true,  'minWords' => 4, 'name' => 'Hasło dobre (bez polskich diakrytycznych)'],
    ['key' => 'long',        'min' => 24, 'max' => 32, 'strip' => false, 'minWords' => 3, 'name' => 'Hasło długie'],
    ['key' => 'ascii-short', 'min' => 20, 'max' => 26, 'strip' => true,  'minWords' => 3, 'name' => 'Hasło krótkie (bez polskich diakrytycznych)'],
    ['key' => 'medium',      'min' => 13, 'max' => 16, 'strip' => false, 'minWords' => 2, 'name' => 'Hasło średnie'],
];

function randomItem(array $arr): mixed
{
    return $arr[array_rand($arr)];
}

function removePunctuation(string $text): string
{
    return preg_replace('/[.,;:!?„""\'\'«»()\[\]{}<>—–\-\/\\\\…\'"]/u', '', $text);
}

function normalizeWord(string $word, ?string $forceCase): string
{
    if ($forceCase === 'lower') return mb_strtolower($word);
    if ($forceCase === 'upper') return mb_strtoupper($word);
    return random_int(0, 1) === 0 ? mb_strtolower($word) : mb_strtoupper($word);
}

function stripDiacritics(string $value): string
{
    $map = [
        'ł' => 'l', 'Ł' => 'L', 'ą' => 'a', 'Ą' => 'A',
        'ę' => 'e', 'Ę' => 'E', 'ó' => 'o', 'Ó' => 'O',
        'ś' => 's', 'Ś' => 'S', 'ź' => 'z', 'Ź' => 'Z',
        'ż' => 'z', 'Ż' => 'Z', 'ć' => 'c', 'Ć' => 'C',
        'ń' => 'n', 'Ń' => 'N',
    ];
    $result = strtr($value, $map);
    if (class_exists('Normalizer')) {
        $result = \Normalizer::normalize($result, \Normalizer::NFD);
        $result = preg_replace('/[\x{0300}-\x{036f}]/u', '', $result);
    }
    return $result;
}

function transcribePassword(string $password): string
{
    $normalized = stripDiacritics($password);
    $parts      = [];
    $chars      = mb_str_split($password);
    $normChars  = mb_str_split($normalized);

    foreach ($chars as $i => $orig) {
        $ch    = $normChars[$i] ?? $orig;
        $lower = mb_strtolower($ch);

        if (isset(ICAO_PL[$lower])) {
            $isUpper = $orig !== mb_strtolower($orig);
            $parts[] = ($isUpper ? 'duża' : 'mała') . ' ' . ICAO_PL[$lower];
        } elseif (isset(DIGIT_NAMES[$ch])) {
            $parts[] = 'cyfra ' . DIGIT_NAMES[$ch];
        } else {
            $parts[] = 'znak ' . $ch;
        }
    }

    return implode(' | ', $parts);
}

function ensureDigitAndSpecial(string $value): string
{
    if (!preg_match('/\d/', $value)) {
        $value .= randomItem(str_split(DIGITS));
    }
    if (!preg_match('/[!@#$%^&*()\-_=+\[\]{};:,.?\/|]/', $value)) {
        $value .= randomItem(str_split(SPECIALS));
    }
    return $value;
}

function isValidLine(string $line): bool
{
    $trimmed = trim($line);
    if (mb_strlen($trimmed) < MIN_CHARS_IN_LINE) return false;
    $words = array_filter(preg_split('/\s+/u', $trimmed));
    return count($words) >= MIN_WORDS_IN_LINE;
}

function extractWordsFromLine(string $line): array
{
    $parts = preg_split('/\s+/u', trim($line));
    $words = [];
    foreach ($parts as $part) {
        $word = trim(removePunctuation($part));
        if ($word !== '') {
            $words[] = $word;
        }
    }
    return $words;
}

function loadSourceFiles(): array
{
    $path = __DIR__ . '/sources.json';
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function loadLinesFromFile(string $filePath): array
{
    $full = __DIR__ . '/' . $filePath;
    if (!file_exists($full)) return [];
    $lines = preg_split('/\r?\n/', file_get_contents($full));
    return array_values(array_filter(array_map('trim', $lines), 'isValidLine'));
}

function buildPassword(int $min, int $max, bool $strip, int $minWords, array $lines, array $excluded = []): ?array
{
    if (empty($lines)) return null;

    $digitsArr   = str_split(DIGITS);
    $specialsArr = str_split(SPECIALS);

    for ($attempt = 0; $attempt < MAX_PASSWORD_ATTEMPTS; $attempt++) {
        $line      = randomItem($lines);
        $lineWords = extractWordsFromLine($line);
        $count     = count($lineWords);

        if ($count < $minWords) continue;

        $wordCount  = random_int($minWords, min($count, $minWords + 2));
        $startIndex = random_int(0, $count - $wordCount);
        $selected   = array_slice($lineWords, $startIndex, $wordCount);

        // Pomiń, jeśli którekolwiek wybrane słowo jest wykluczone
        $hasExcluded = false;
        foreach ($selected as $word) {
            if (isset($excluded[mb_strtolower($word)])) {
                $hasExcluded = true;
                break;
            }
        }
        if ($hasExcluded) continue;

        $separators = [];
        for ($i = 0; $i < $wordCount - 1; $i++) {
            $separators[] = random_int(0, 1) === 0 ? randomItem($digitsArr) : randomItem($specialsArr);
        }

        $cases      = array_fill(0, $wordCount, null);
        $lowerIdx   = random_int(0, $wordCount - 1);
        $upperIdx   = random_int(0, $wordCount - 1);
        while ($wordCount > 1 && $upperIdx === $lowerIdx) {
            $upperIdx = random_int(0, $wordCount - 1);
        }
        $cases[$lowerIdx] = 'lower';
        $cases[$upperIdx] = 'upper';

        $parts    = array_map(fn($w, $c) => normalizeWord($w, $c), $selected, $cases);
        $password = $parts[0];
        for ($i = 0; $i < count($separators); $i++) {
            $password .= $separators[$i] . $parts[$i + 1];
        }

        if ($strip) {
            $password = stripDiacritics($password);
        }

        $password = ensureDigitAndSpecial($password);
        $len      = mb_strlen($password);

        if ($len >= $min && $len <= $max) {
            $words = array_map(fn($w) => mb_strtolower($w), $selected);
            return ['password' => $password, 'sentence' => $line, 'words' => $words];
        }
    }

    return null;
}

/**
 * Generuje pojedynczy wariant hasła (losowy plik źródłowy + buildPassword).
 * Zwraca tablicę z polami name/password/length/sentence/transcription/words
 * albo z polem error.
 */
function generateVariant(array $variant, array $sourceFiles, array $excluded): array
{
    $lines = loadLinesFromFile(randomItem($sourceFiles));

    if (empty($lines)) {
        return ['name' => $variant['name'], 'error' => 'Za mało zdań do losowania'];
    }

    $result = buildPassword(
        $variant['min'],
        $variant['max'],
        $variant['strip'],
        $variant['minWords'],
        $lines,
        $excluded
    );

    if ($result === null) {
        return ['name' => $variant['name'], 'error' => 'Nie udało się wygenerować hasła'];
    }

    return [
        'name'          => $variant['name'],
        'password'      => $result['password'],
        'length'        => mb_strlen($result['password']),
        'sentence'      => $result['sentence'],
        'transcription' => transcribePassword($result['password']),
        'words'         => $result['words'],
    ];
}

/** Zwraca definicję wariantu po kluczu albo null. */
function findVariant(string $key): ?array
{
    foreach (VARIANTS as $variant) {
        if ($variant['key'] === $key) return $variant;
    }
    return null;
}

/** Kończy działanie z błędem JSON i podanym kodem HTTP. */
function jsonError(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Czyta listę wykluczonych słów z ciała POST (application/json). */
function readExcludedWords(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return [];

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['exclude']) || !is_array($data['exclude'])) {
        return [];
    }

    $excluded = [];
    foreach ($data['exclude'] as $word) {
        if (is_string($word) && $word !== '') {
            $excluded[mb_strtolower($word)] = true;
        }
    }
    return $excluded;
}

// === Main ===

$sourceFiles = loadSourceFiles();

if (empty($sourceFiles)) {
    jsonError('Brak plików źródłowych', 500);
}

// --- Parametry zapytania ---
$variantKey = isset($_GET['variant']) ? trim((string)$_GET['variant']) : null;
$asText     = isset($_GET['raw']) || (isset($_GET['format']) && strtolower((string)$_GET['format']) === 'text');

$count = 1;
if (isset($_GET['count'])) {
    $count = filter_var($_GET['count'], FILTER_VALIDATE_INT);
    if ($count === false || $count < 1 || $count > 20) {
        jsonError('Parametr count musi być liczbą całkowitą od 1 do 20');
    }
}

// Walidacja wariantu (jeśli podany)
$selectedVariant = null;
if ($variantKey !== null && $variantKey !== '') {
    $selectedVariant = findVariant($variantKey);
    if ($selectedVariant === null) {
        $keys = implode(', ', array_column(VARIANTS, 'key'));
        jsonError("Nieznany wariant „{$variantKey}”. Dostępne warianty: {$keys}");
    }
}

$excluded = readExcludedWords();

// --- Generowanie ---
// Z wariantem: tablica $count haseł tego wariantu.
// Bez wariantu: $count zestawów wszystkich wariantów (1 zestaw => zwykły obiekt).
if ($selectedVariant !== null) {
    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $items[] = generateVariant($selectedVariant, $sourceFiles, $excluded);
    }
} else {
    $items = [];
    for ($i = 0; $i < $count; $i++) {
        $set = [];
        foreach (VARIANTS as $variant) {
            $set[$variant['key']] = generateVariant($variant, $sourceFiles, $excluded);
        }
        $items[] = $set;
    }
}

// --- Format tekstowy (raw) — tylko hasła, po jednym na linię ---
if ($asText) {
    header('Content-Type: text/plain; charset=utf-8');
    $out = [];
    foreach ($items as $item) {
        if ($selectedVariant !== null) {
            if (isset($item['password'])) $out[] = $item['password'];
        } else {
            foreach ($item as $entry) {
                if (isset($entry['password'])) $out[] = $entry['password'];
            }
        }
    }
    echo implode("\n", $out) . "\n";
    exit;
}

// --- Format JSON ---
header('Content-Type: application/json; charset=utf-8');

$generated = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);

if ($selectedVariant !== null) {
    // Pojedynczy wariant: count==1 => obiekt, count>1 => tablica
    $payload = ['generated' => $generated, 'variant' => $selectedVariant['key']];
    $payload['passwords'] = $count === 1 ? $items[0] : $items;
} else {
    // Wszystkie warianty: count==1 => obiekt zestawu, count>1 => tablica zestawów
    $payload = ['generated' => $generated];
    $payload['passwords'] = $count === 1 ? $items[0] : $items;
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
