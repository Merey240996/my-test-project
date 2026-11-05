<?php
declare(strict_types=1);

$startedAt = microtime(true);
$sourceFile = '/srv/projects/crm/export/client.jsonl';
$basesFile  = '/srv/projects/crm/export/database.jsonl';
$envFile    = '/srv/projects/mf/crm/src/.env';
$errorFile  = __DIR__ . '/04_import_clients_errors.log';
$targetDb   = 'crm2_old_20261020';

const CLIENT_BATCH_SIZE = 500;
const VALUE_BATCH_SIZE = 2000;
const COMMIT_EVERY = 5000;

if (!is_file($sourceFile) || !is_file($basesFile) || !is_file($envFile)) {
    exit("ERROR: не найден файл импорта или .env\n");
}

file_put_contents(
    $errorFile,
    'Импорт начат: ' . date('Y-m-d H:i:s') . PHP_EOL
);

$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim(trim($value), "\"'");
}

$host = $env['MYSQL_HOST'] ?? '127.0.0.1';
$port = $env['MYSQL_PORT'] ?? '3306';
$user = $env['MYSQL_USER'] ?? '';
$pass = $env['MYSQL_PASSWORD'] ?? '';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$targetDb};charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]
);

$pdo->exec('SET SESSION foreign_key_checks = 0');
$pdo->exec('SET SESSION unique_checks = 0');

function logError(string $message): void
{
    global $errorFile;

    file_put_contents(
        $errorFile,
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

function toDateTime(array $row, string $textKey, string $unixKey): string
{
    if (!empty($row[$textKey])) {
        $timestamp = strtotime((string)$row[$textKey]);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
    }

    if (isset($row[$unixKey]) && is_numeric($row[$unixKey])) {
        return date('Y-m-d H:i:s', (int)$row[$unixKey]);
    }

    return '1970-01-01 00:00:00';
}

function scalarValue($value): ?string
{
    if ($value === null || is_array($value) || is_object($value)) {
        return null;
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $value = (string)$value;
    return $value === '' ? null : $value;
}

function makeInsertSql(string $table, array $columns, int $rowCount): string
{
    $oneRow = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';

    return 'INSERT IGNORE INTO `' . $table . '` (`'
        . implode('`,`', $columns) . '`) VALUES '
        . implode(',', array_fill(0, $rowCount, $oneRow));
}

/*
 * Пакетная вставка.
 * При ошибке SQL выполняется fallback по одной записи,
 * чтобы изолировать проблемные строки и продолжить импорт.
 */
function flushBatch(
    PDO $pdo,
    string $table,
    array $columns,
    array &$batch,
    int &$imported,
    int &$skipped
): void {
    if (!$batch) {
        return;
    }

    $rows = $batch;
    $batch = [];

    try {
        $sql = makeInsertSql($table, $columns, count($rows));
        $stmt = $pdo->prepare($sql);

        $params = [];
        foreach ($rows as $item) {
            foreach ($item['data'] as $value) {
                $params[] = $value;
            }
        }

        $stmt->execute($params);
        $inserted = $stmt->rowCount();
        $imported += $inserted;
        $skipped += count($rows) - $inserted;
        return;
    } catch (Throwable $e) {
        logError(
            "Пачка {$table}, строк источника "
            . $rows[0]['line'] . '-' . $rows[count($rows) - 1]['line']
            . ': ' . $e->getMessage()
            . '; повторная проверка по одной записи'
        );
    }

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = 'INSERT IGNORE INTO `' . $table . '` (`'
        . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);

    foreach ($rows as $item) {
        try {
            $stmt->execute($item['data']);

            if ($stmt->rowCount() > 0) {
                $imported++;
            } else {
                $skipped++;
            }
        } catch (Throwable $e) {
            $skipped++;
            logError(
                "Таблица {$table}, строка источника {$item['line']}: "
                . $e->getMessage()
            );
        }
    }
}

/*
 * Собираем соответствие имён полей новым client_base_fields.pkid.
 */
$targetFields = [];
$stmt = $pdo->query(
    'SELECT pkid, base_pkid, name
     FROM client_base_fields
     WHERE is_deleted = 0'
);

foreach ($stmt as $field) {
    $targetFields[(string)$field['base_pkid']][(string)$field['name']]
        = (int)$field['pkid'];
}

$fieldAliases = [];
$handle = fopen($basesFile, 'rb');

while (($line = fgets($handle)) !== false) {
    $base = json_decode($line, true);

    if (!is_array($base)) {
        continue;
    }

    $basePkid = (string)($base['pkid'] ?? '');

    if ($basePkid === '' || empty($base['fields']) || !is_array($base['fields'])) {
        continue;
    }

    foreach ($base['fields'] as $field) {
        if (!is_array($field)) {
            continue;
        }

        $legacyName = (string)($field['name'] ?? '');
        $apiName = (string)($field['api_name'] ?? '');

        $newFieldPkid = null;

        if ($legacyName !== '') {
            $newFieldPkid = $targetFields[$basePkid][$legacyName] ?? null;
        }

        if ($newFieldPkid === null && $apiName !== '') {
            $newFieldPkid = $targetFields[$basePkid][$apiName] ?? null;
        }

        if ($newFieldPkid === null) {
            continue;
        }

        if ($legacyName !== '') {
            $fieldAliases[$basePkid][$legacyName] = $newFieldPkid;
        }

        if ($apiName !== '') {
            $fieldAliases[$basePkid][$apiName] = $newFieldPkid;
        }
    }
}

fclose($handle);

$clientColumns = [
    'pkid', 'company_pkid', 'base_pkid', 'user_pkid', 'contact_pkid',
    'user_login', 'company', 'email', 'phone', 'date', 'is_deleted',
    'gmtimeAdd', 'gmtimeChange', 'ugmtimeAdd', 'ugmtimeChange',
];

$valueColumns = [
    'company_pkid', 'client_pkid', 'field_pkid', 'value', 'isDeleted',
    'gmtimeAdd', 'gmtimeChange', 'ugmtimeAdd', 'ugmtimeChange',
];

$clientsBatch = [];
$valuesBatch = [];

$clientsImported = 0;
$clientsSkipped = 0;
$valuesImported = 0;
$valuesSkipped = 0;
$invalidRows = 0;
$lineNo = 0;
$lastProgress = 0;

$excludedKeys = array_fill_keys([
    '_id', 'pkid', 'database', 'historyDatabase', 'contacts',
    'relation', 'maps_point', 'tags', 'login', 'text',
    'client_number', 'companyPkid', 'company_name', 'fname',
    'lname', 'sname', 'primary_login', 'operator', 'operator1',
    'operator11', 'main_email', 'main_phone', 'userCompanyId',
    'isDeleted', 'isDraft', 'isHash', 'isProcessEdit',
    'gmtimeAdd', 'gmtimeChange', 'ugmtimeAdd', 'ugmtimeChange',
    'txVersion', 'date',
], true);

$handle = fopen($sourceFile, 'rb');

if (!$handle) {
    exit("ERROR: не удалось открыть {$sourceFile}\n");
}

$pdo->beginTransaction();

try {
    while (($line = fgets($handle)) !== false) {
        $lineNo++;

        $row = json_decode($line, true);

        if (!is_array($row) || !isset($row['pkid'])) {
            $invalidRows++;
            logError("Некорректный JSON или отсутствует pkid; строка {$lineNo}");
            continue;
        }

        $clientPkid = (int)$row['pkid'];
        $basePkid = (int)($row['database'][0] ?? 0);

        if ($clientPkid <= 0 || $basePkid <= 0) {
            $invalidRows++;
            logError("Некорректный pkid/base_pkid; строка {$lineNo}");
            continue;
        }

        $companyPkid = (int)($row['userCompanyId'] ?? $row['companyPkid'] ?? 1);
        if ($companyPkid <= 0) {
            $companyPkid = 1;
        }

        $login = $row['login'] ?? null;
        if (is_array($login)) {
            $login = $login[0] ?? null;
        }

        $date = null;
        if (!empty($row['date'])) {
            $timestamp = strtotime((string)$row['date']);
            if ($timestamp !== false) {
                $date = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $gmtimeAdd = toDateTime($row, 'gmtimeAdd', 'ugmtimeAdd');
        $gmtimeChange = toDateTime($row, 'gmtimeChange', 'ugmtimeChange');
        $ugmtimeAdd = (int)($row['ugmtimeAdd'] ?? strtotime($gmtimeAdd));
        $ugmtimeChange = (int)($row['ugmtimeChange'] ?? strtotime($gmtimeChange));

        $clientsBatch[] = [
            'line' => $lineNo,
            'data' => [
                $clientPkid,
                $companyPkid,
                $basePkid,
                isset($row['operator']) && is_numeric($row['operator'])
                    ? (int)$row['operator'] : null,
                null,
                scalarValue($login),
                scalarValue($row['company_name'] ?? null),
                scalarValue($row['main_email'] ?? null),
                scalarValue($row['main_phone'] ?? null),
                $date,
                !empty($row['isDeleted']) ? 1 : 0,
                $gmtimeAdd,
                $gmtimeChange,
                max(0, $ugmtimeAdd),
                max(0, $ugmtimeChange),
            ],
        ];

        foreach ($row as $key => $value) {
            if (isset($excludedKeys[$key])) {
                continue;
            }

            $fieldPkid = $fieldAliases[(string)$basePkid][(string)$key] ?? null;
            $fieldValue = scalarValue($value);

            if ($fieldPkid === null || $fieldValue === null) {
                $valuesSkipped++;
                continue;
            }

            $valuesBatch[] = [
                'line' => $lineNo,
                'data' => [
                    $companyPkid,
                    $clientPkid,
                    $fieldPkid,
                    $fieldValue,
                    !empty($row['isDeleted']) ? 1 : 0,
                    $gmtimeAdd,
                    $gmtimeChange,
                    max(0, $ugmtimeAdd),
                    max(0, $ugmtimeChange),
                ],
            ];

            if (count($valuesBatch) >= VALUE_BATCH_SIZE) {
                flushBatch(
                    $pdo, 'client_field_values', $valueColumns, $valuesBatch,
                    $valuesImported, $valuesSkipped
                );
            }
        }

        if (count($clientsBatch) >= CLIENT_BATCH_SIZE) {
            flushBatch(
                $pdo, 'clients', $clientColumns, $clientsBatch,
                $clientsImported, $clientsSkipped
            );
        }

        if ($lineNo % COMMIT_EVERY === 0) {
            flushBatch(
                $pdo, 'clients', $clientColumns, $clientsBatch,
                $clientsImported, $clientsSkipped
            );
            flushBatch(
                $pdo, 'client_field_values', $valueColumns, $valuesBatch,
                $valuesImported, $valuesSkipped
            );

            $pdo->commit();
            $pdo->beginTransaction();

            $elapsed = max(1, microtime(true) - $startedAt);
            printf(
                "[%s] Строк: %d; клиентов: %d; значений: %d; скорость: %.0f строк/сек\n",
                gmdate('H:i:s', (int)$elapsed),
                $lineNo,
                $clientsImported,
                $valuesImported,
                $lineNo / $elapsed
            );

            $lastProgress = $lineNo;
        }
    }

    flushBatch(
        $pdo, 'clients', $clientColumns, $clientsBatch,
        $clientsImported, $clientsSkipped
    );
    flushBatch(
        $pdo, 'client_field_values', $valueColumns, $valuesBatch,
        $valuesImported, $valuesSkipped
    );

    $pdo->commit();
    fclose($handle);

} catch (Throwable $e) {
    if (is_resource($handle)) {
        fclose($handle);
    }

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    logError("Критическая ошибка на строке {$lineNo}: " . $e->getMessage());
    echo "Критическая ошибка на строке {$lineNo}: {$e->getMessage()}\n";
    echo "Предыдущие завершённые транзакции сохранены.\n";
    exit(1);
} finally {
    $pdo->exec('SET SESSION foreign_key_checks = 1');
    $pdo->exec('SET SESSION unique_checks = 1');
}

$elapsed = (int)round(microtime(true) - $startedAt);

printf("\nВремя импорта: %02d:%02d:%02d\n",
    intdiv($elapsed, 3600),
    intdiv($elapsed % 3600, 60),
    $elapsed % 60
);

printf("Строк обработано: %d\n", $lineNo);
printf("Клиентов добавлено: %d\n", $clientsImported);
printf("Клиентов пропущено: %d\n", $clientsSkipped);
printf("Значений полей добавлено: %d\n", $valuesImported);
printf("Значений полей пропущено: %d\n", $valuesSkipped);
printf("Некорректных строк: %d\n", $invalidRows);
printf("Средняя скорость: %.0f строк/сек\n", $lineNo / max(1, $elapsed));
printf("Файл ошибок: %s\n", $errorFile);
printf("Целевая БД: %s\n", $targetDb);
