<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

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

function buildPassword(int $min, int $max, bool $strip, int $minWords, array $lines): ?array
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
            return ['password' => $password, 'sentence' => $line];
        }
    }

    return null;
}

// === Main ===

$sourceFiles = loadSourceFiles();

if (empty($sourceFiles)) {
    echo json_encode(['error' => 'Brak plików źródłowych'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$passwords = [];

foreach (VARIANTS as $variant) {
    $lines = loadLinesFromFile(randomItem($sourceFiles));

    if (empty($lines)) {
        $passwords[$variant['key']] = ['error' => 'Za mało zdań do losowania'];
        continue;
    }

    $result = buildPassword($variant['min'], $variant['max'], $variant['strip'], $variant['minWords'], $lines);

    if ($result === null) {
        $passwords[$variant['key']] = ['error' => 'Nie udało się wygenerować hasła'];
        continue;
    }

    $passwords[$variant['key']] = [
        'name'          => $variant['name'],
        'password'      => $result['password'],
        'length'        => mb_strlen($result['password']),
        'sentence'      => $result['sentence'],
        'transcription' => transcribePassword($result['password']),
    ];
}

echo json_encode(
    ['generated' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM), 'passwords' => $passwords],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
