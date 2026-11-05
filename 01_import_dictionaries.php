<?php

declare(strict_types=1);

$projectDir = '/srv/projects/mf/crm/src';
$envFile = $projectDir . '/.env';
$targetDb = 'crm2_old_20261020';
$sourceFile = '/srv/projects/crm/export/list.jsonl';

if (!is_file($envFile) || !is_readable($envFile)) {
    exit("ERROR: .env не найден или недоступен\n");
}
if (!is_file($sourceFile) || !is_readable($sourceFile)) {
    exit("ERROR: list.jsonl не найден или недоступен\n");
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

function legacyDateTime(array $row, string $field): string
{
    $value = $row[$field] ?? null;
    if (!$value) {
        return '1970-01-01 00:00:00';
    }

    $timestamp = strtotime((string)$value);
    return $timestamp === false
        ? '1970-01-01 00:00:00'
        : gmdate('Y-m-d H:i:s', $timestamp);
}

function legacyTimestamp(array $row, string $field): int
{
    $value = $row[$field] ?? null;
    return ($value === null || $value === '') ? 0 : max(0, (int)$value);
}

$insertDictionary = $pdo->prepare(
    'INSERT INTO dictionaries
    (pkid, company_pkid, name, description, is_deleted,
     gmtimeAdd, gmtimeChange, ugmtimeAdd, ugmtimeChange)
    VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        company_pkid = VALUES(company_pkid),
        name = VALUES(name),
        is_deleted = VALUES(is_deleted),
        gmtimeAdd = VALUES(gmtimeAdd),
        gmtimeChange = VALUES(gmtimeChange),
        ugmtimeAdd = VALUES(ugmtimeAdd),
        ugmtimeChange = VALUES(ugmtimeChange)'
);

$insertValue = $pdo->prepare(
    'INSERT INTO dictionary_values
    (company_pkid, dictionary_pkid, value, sortOrder,
     is_deleted, gmtimeAdd, gmtimeChange, ugmtimeAdd, ugmtimeChange)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$dictionaryCount = 0;
$valueCount = 0;
$skippedValues = 0;
$lineNumber = 0;

$pdo->beginTransaction();

try {
    $handle = fopen($sourceFile, 'rb');

    while (($line = fgets($handle)) !== false) {
        $lineNumber++;

        if (trim($line) === '') {
            continue;
        }

        $row = json_decode($line, true);
        if (!is_array($row) || !isset($row['pkid'])) {
            throw new RuntimeException("Некорректная запись, строка {$lineNumber}");
        }

        $dictionaryId = (int)$row['pkid'];
        $companyId = (int)($row['userCompanyId'] ?? 0);

        if ($dictionaryId <= 0 || $companyId < 0) {
            throw new RuntimeException("Некорректные ID, строка {$lineNumber}");
        }

        $added = legacyDateTime($row, 'gmtimeAdd');
        $changed = legacyDateTime($row, 'gmtimeChange');
        $addedTs = legacyTimestamp($row, 'ugmtimeAdd');
        $changedTs = legacyTimestamp($row, 'ugmtimeChange');

        $insertDictionary->execute([
            $dictionaryId,
            $companyId,
            mb_substr((string)($row['name'] ?? ''), 0, 255),
            !empty($row['isDeleted']) ? 1 : 0,
            $added,
            $changed,
            $addedTs,
            $changedTs,
        ]);

        $dictionaryCount++;

        $items = $row['data'] ?? [];
        if (!is_array($items)) {
            $skippedValues++;
            continue;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                $skippedValues++;
                continue;
            }

            $value = $item['data'] ?? null;
            if ($value === null || trim((string)$value) === '') {
                $skippedValues++;
                continue;
            }

            $value = mb_substr((string)$value, 0, 255);
            $position = $item['pos'] ?? null;
            $sortOrder = is_numeric($position) ? (int)$position : 0;
            $isDeleted = !empty($item['isDeleted']) ? 1 : 0;

            $insertValue->execute([
                $companyId,
                $dictionaryId,
                $value,
                $sortOrder,
                $isDeleted,
                $added,
                $changed,
                $addedTs,
                $changedTs,
            ]);

            $valueCount++;
        }
    }

    fclose($handle);
    $pdo->commit();

    echo "Импорт завершён\n";
    echo "Справочников обработано: {$dictionaryCount}\n";
    echo "Значений добавлено: {$valueCount}\n";
    echo "Пустых или пропущенных значений: {$skippedValues}\n";
    echo "Целевая БД: {$targetDb}\n";
} catch (Throwable $e) {
    if (isset($handle) && is_resource($handle)) {
        fclose($handle);
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "ERROR, строка {$lineNumber}: {$e->getMessage()}\n");
    exit(1);
}
