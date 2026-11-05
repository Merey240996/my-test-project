<?php

declare(strict_types=1);

$envFile = '/srv/projects/mf/crm/src/.env';
$sourceFile = '/srv/projects/crm/export/database.jsonl';
$targetDb = 'crm2_old_20261020';

if (!is_readable($envFile) || !is_readable($sourceFile)) {
    exit("ERROR: .env или database.jsonl недоступен\n");
}

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim(trim($value), "\"'");
}

$host = $env['MYSQL_HOST'] ?? '';
$port = $env['MYSQL_PORT'] ?? '3306';
$user = $env['MYSQL_USER'] ?? '';
$password = $env['MYSQL_PASSWORD'] ?? '';

if ($host === '' || $user === '') {
    exit("ERROR: не найдены MYSQL_HOST или MYSQL_USER\n");
}

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$targetDb};charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

function legacyDateTime(array $row, string $field, string $fallback = '1970-01-01 00:00:00'): string
{
    $value = $row[$field] ?? null;

    if (!$value) {
        return $fallback;
    }

    $timestamp = strtotime((string)$value);

    return $timestamp === false ? $fallback : gmdate('Y-m-d H:i:s', $timestamp);
}

function legacyTimestamp(array $row, string $field, int $fallback = 0): int
{
    $value = $row[$field] ?? null;

    if ($value === null || $value === '') {
        return $fallback;
    }

    if (is_numeric($value)) {
        return max(0, (int)$value);
    }

    $timestamp = strtotime((string)$value);

    return $timestamp === false ? $fallback : max(0, $timestamp);
}

$typeMap = [
    1  => 1,  // Строка
    2  => 2,  // Текст
    3  => 3,  // Дата
    6  => 2,  // История и произвольный текст
    7  => 5,  // Справочник
    8  => 6,  // Телефон
    9  => 7,  // E-mail
    10 => 8,  // Денежные значения
    11 => 2,  // Город
    12 => 2,  // Страна
    18 => 2,  // Кто зарегистрировал
    19 => 10, // Файл
    20 => 9,  // Изображение
    21 => 10, // Файл
];

$baseInsert = $pdo->prepare(
    'INSERT INTO client_bases
    (pkid, company_pkid, name, is_company, is_deleted,
     gmtimeAdd, gmtimeChange, ugmtimeAdd, ugmtimeChange)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$fieldInsert = $pdo->prepare(
    'INSERT INTO client_base_fields
    (company_pkid, base_pkid, name, fieldType, dictionary_pkid,
     is_hidden, show_in_mobile, is_searchable, sortOrder, is_deleted,
     gmtimeAdd, gmtimeChange, ugmtimeAdd, ugmtimeChange)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$baseCount = 0;
$fieldCount = 0;
$unknownTypes = [];
$lineNumber = 0;
$handle = null;

$pdo->beginTransaction();

try {
    $handle = fopen($sourceFile, 'rb');

    if (!$handle) {
        throw new RuntimeException('Не удалось открыть database.jsonl');
    }

    while (($line = fgets($handle)) !== false) {
        $lineNumber++;

        if (trim($line) === '') {
            continue;
        }

        $doc = json_decode($line, true);

        if (!is_array($doc) || !isset($doc['pkid'])) {
            throw new RuntimeException("Некорректный JSON или отсутствует pkid");
        }

        $baseId = (int)$doc['pkid'];
        $companyId = (int)($doc['userCompanyId'] ?? 0);

        if ($baseId <= 0 || $companyId < 0) {
            throw new RuntimeException('Некорректный ID базы или компании');
        }

        $baseAdd = legacyDateTime($doc, 'gmtimeAdd');
        $baseChange = legacyDateTime($doc, 'gmtimeChange', $baseAdd);
        $baseAddTs = legacyTimestamp($doc, 'ugmtimeAdd');
        $baseChangeTs = legacyTimestamp($doc, 'ugmtimeChange', $baseAddTs);

        $baseInsert->execute([
            $baseId,
            $companyId,
            mb_substr((string)($doc['name'] ?? $doc['api_name'] ?? 'Без названия'), 0, 255),
            !empty($doc['isCompany']) ? 1 : 0,
            !empty($doc['isDeleted']) ? 1 : 0,
            $baseAdd,
            $baseChange,
            $baseAddTs,
            $baseChangeTs,
        ]);

        $baseCount++;

        $fields = $doc['fields'] ?? [];

        if (!is_array($fields)) {
            continue;
        }

        foreach ($fields as $index => $field) {
            if (!is_array($field) || !isset($field['pos'])) {
                continue;
            }

            // pos — ID поля; foreignPkid — ID родительской базы.
            $fieldId = (int)$field['pos'];

            if ($fieldId <= 0) {
                continue;
            }

            $legacyType = isset($field['type']) ? (int)$field['type'] : null;

            if ($legacyType !== null && isset($typeMap[$legacyType])) {
                $fieldType = $typeMap[$legacyType];
            } else {
                $fieldType = 2;
                $unknownTypes[(string)$legacyType] = ($unknownTypes[(string)$legacyType] ?? 0) + 1;
            }

            $listId = $field['list'] ?? null;
            $dictionaryId = null;

            if ($listId !== null && $listId !== '') {
                $dictionaryId = (int)$listId;

                // Не создаём ссылку на несуществующий справочник.
                $check = $pdo->prepare('SELECT 1 FROM dictionaries WHERE pkid = ?');
                $check->execute([$dictionaryId]);

                if (!$check->fetchColumn()) {
                    $dictionaryId = null;
                }
            }

            $fieldAdd = legacyDateTime($field, 'gmtimeAdd', $baseAdd);
            $fieldChange = legacyDateTime($field, 'gmtimeChange', $baseChange);
            $fieldAddTs = legacyTimestamp($field, 'ugmtimeAdd', $baseAddTs);
            $fieldChangeTs = legacyTimestamp($field, 'ugmtimeChange', $baseChangeTs);

            $fieldInsert->execute([
                $companyId,
                $baseId,
                mb_substr((string)($field['name'] ?? $field['api_name'] ?? 'Без названия'), 0, 255),
                $fieldType,
                $dictionaryId,
                !empty($field['hidden']) ? 1 : 0,
                !empty($field['mobileSearchVisible']) ? 1 : 0,
                !empty($field['search']) ? 1 : 0,
                is_numeric($index) ? (int)$index : 0,
                !empty($field['isDeleted']) ? 1 : 0,
                $fieldAdd,
                $fieldChange,
                $fieldAddTs,
                $fieldChangeTs,
            ]);

            $fieldCount++;
        }
    }

    fclose($handle);
    $handle = null;

    $pdo->commit();

    echo "Импорт баз и полей завершён\n";
    echo "Баз обработано: {$baseCount}\n";
    echo "Полей импортировано: {$fieldCount}\n";
    echo "Целевая БД: {$targetDb}\n";

    if ($unknownTypes) {
        echo "Неизвестные типы полей (пока преобразованы в текст): "
            . json_encode($unknownTypes, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (Throwable $e) {
    if (is_resource($handle)) {
        fclose($handle);
    }

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "ERROR, строка {$lineNumber}: {$e->getMessage()}\n");
    exit(1);
}
