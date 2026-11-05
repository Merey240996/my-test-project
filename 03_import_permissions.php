<?php

declare(strict_types=1);

$projectDir = '/srv/projects/mf/crm/src';
$sourceFile = '/srv/projects/crm/export/databasePerm.jsonl';
$targetDb = 'crm2_old_20261020';

function readEnvFile(string $path): array
{
    $result = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $result[trim($key)] = trim(trim($value), "\"'");
    }
    return $result;
}

function toDateTime(mixed $value): string
{
    if (!is_string($value) || trim($value) === '') {
        return '1970-01-01 00:00:00';
    }

    $timestamp = strtotime($value);
    return $timestamp === false
        ? '1970-01-01 00:00:00'
        : gmdate('Y-m-d H:i:s', $timestamp);
}

$env = readEnvFile($projectDir . '/.env');

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env['MYSQL_HOST'] ?? '127.0.0.1',
        $env['MYSQL_PORT'] ?? '3306',
        $targetDb
    ),
    $env['MYSQL_USER'] ?? '',
    $env['MYSQL_PASSWORD'] ?? '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

if (!is_file($sourceFile)) {
    throw new RuntimeException("Файл не найден: {$sourceFile}");
}

$baseCheck = $pdo->prepare(
    'SELECT pkid FROM client_bases WHERE pkid = ?'
);

$insert = $pdo->prepare(
    'INSERT IGNORE INTO client_base_permissions
    (company_pkid, base_pkid, role_pkid, is_deleted,
     gmtimeAdd, gmtimeChange, ugmtimeAdd, ugmtimeChange)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$processed = 0;
$imported = 0;
$skipped = 0;

$pdo->beginTransaction();

try {
    $handle = fopen($sourceFile, 'rb');

    if ($handle === false) {
        throw new RuntimeException("Не удалось открыть: {$sourceFile}");
    }

    while (($line = fgets($handle)) !== false) {
        if (trim($line) === '') {
            continue;
        }

        $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $processed++;

        $baseId = (int)($row['databasePkid'] ?? 0);
        $roleId = (int)($row['account_pkid'] ?? 0);
        $companyId = (int)($row['userCompanyId'] ?? 1);

        if ($baseId <= 0 || $roleId <= 0) {
            $skipped++;
            continue;
        }

        $baseCheck->execute([$baseId]);

        if (!$baseCheck->fetchColumn()) {
            $skipped++;
            continue;
        }

        $insert->execute([
            $companyId > 0 ? $companyId : 1,
            $baseId,
            $roleId,
            !empty($row['isDeleted']) ? 1 : 0,
            toDateTime($row['gmtimeAdd'] ?? null),
            toDateTime($row['gmtimeChange'] ?? null),
            max(0, (int)($row['ugmtimeAdd'] ?? 0)),
            max(0, (int)($row['ugmtimeChange'] ?? 0)),
        ]);

        if ($insert->rowCount() > 0) {
            $imported++;
        } else {
            $skipped++;
        }
    }

    fclose($handle);
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($handle) && is_resource($handle)) {
        fclose($handle);
    }

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

echo "Импорт прав завершён\n";
echo "Строк обработано: {$processed}\n";
echo "Связей импортировано: {$imported}\n";
echo "Пропущено: {$skipped}\n";
echo "Целевая БД: {$targetDb}\n";
