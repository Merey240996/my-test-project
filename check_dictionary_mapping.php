<?php
declare(strict_types=1);

$projectDir = '/srv/projects/mf/crm/src';
$targetDb = 'crm2_old_20261020';
$sourceFile = '/srv/projects/crm/export/list.jsonl';

$apply = in_array('--apply', $argv, true);
$batchSize = 1000;

// Подключение к тестовой базе
$env = [];

foreach (file($projectDir . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $env[trim($key)] = trim(trim($value), "\"'");
}

$pdo = new PDO(
    "mysql:host={$env['MYSQL_HOST']};port=" .
    ($env['MYSQL_PORT'] ?? '3306') .
    ";dbname={$targetDb};charset=utf8mb4",
    $env['MYSQL_USER'],
    $env['MYSQL_PASSWORD'] ?? '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]
);

$writePdo = new PDO(
    "mysql:host={$env['MYSQL_HOST']};port=" .
    ($env['MYSQL_PORT'] ?? '3306') .
    ";dbname={$targetDb};charset=utf8mb4",
    $env['MYSQL_USER'],
    $env['MYSQL_PASSWORD'] ?? '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

// Загружаем соответствия из MongoDB JSONL
$mapping = [];
$conflicts = [];

$handle = fopen($sourceFile, 'rb');

if ($handle === false) {
    throw new RuntimeException("Не удалось открыть {$sourceFile}");
}

while (($line = fgets($handle)) !== false) {
    if (trim($line) === '') {
        continue;
    }

    $row = json_decode($line, true);

    if (!is_array($row) || !isset($row['pkid'])) {
        continue;
    }

    $dictionaryPkid = (string)$row['pkid'];

    foreach (($row['data'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $pos = $item['pos'] ?? null;
        $value = $item['data'] ?? null;

        if ($pos === null || !is_numeric($pos) ||
            $value === null || trim((string)$value) === '') {
            continue;
        }

        $pos = (string)$pos;
        $value = mb_substr((string)$value, 0, 255);

        $key = $dictionaryPkid . ':' . $pos;

        if (isset($mapping[$dictionaryPkid][$pos]) &&
            $mapping[$dictionaryPkid][$pos] !== $value) {
            $conflicts[$key] = true;
            continue;
        }

        $mapping[$dictionaryPkid][$pos] = $value;
    }
}

fclose($handle);

foreach (array_keys($conflicts) as $key) {
    [$dictionaryPkid, $pos] = explode(':', $key, 2);
    unset($mapping[$dictionaryPkid][$pos]);
}

// Подтверждённые дополнительные соответствия
$additionalMapping = [
    '1373481410231889' => [
        '1373606451338444' => 'не указан',
        '1373481557312544' => 'мужской',
        '1373481561953757' => 'женский',
    ],
    '1382511204199479' => [
        '1382700823911395' => 'не известно',
    ],
];

foreach ($additionalMapping as $dictionaryPkid => $items) {
    foreach ($items as $pos => $value) {
        if (isset($mapping[$dictionaryPkid][$pos]) &&
            $mapping[$dictionaryPkid][$pos] !== $value) {
            throw new RuntimeException(
                "Конфликт: {$dictionaryPkid} / {$pos}"
            );
        }

        $mapping[$dictionaryPkid][$pos] = $value;
    }
}

echo "База: {$targetDb}\n";
echo "Режим: " . ($apply ? 'ОБНОВЛЕНИЕ' : 'ПРОВЕРКА') . "\n";
echo "Справочников: " . count($mapping) . "\n";
echo "Конфликтов: " . count($conflicts) . "\n\n";

// Пакетный UPDATE с проверкой исходного значения
function flushBatch(PDO $pdo, array &$batch): int
{
    if (!$batch) {
        return 0;
    }

    $cases = [];
    $ids = [];
    $params = [];

    foreach ($batch as $item) {
        $cases[] = 'WHEN pkid = ? AND BINARY value = BINARY ? THEN ?';
        $params[] = $item['pkid'];
        $params[] = $item['old'];
        $params[] = $item['new'];
        $ids[] = $item['pkid'];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "
        UPDATE client_field_values
        SET value = CASE
            " . implode("\n", $cases) . "
            ELSE value
        END
        WHERE pkid IN ({$placeholders})
          AND isDeleted = 0
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $ids));

    $affected = $stmt->rowCount();
    $batch = [];

    return $affected;
}

$sql = "
    SELECT
        v.pkid,
        v.value,
        f.dictionary_pkid
    FROM client_field_values v
    JOIN client_base_fields f ON f.pkid = v.field_pkid
    WHERE f.fieldType = 5
      AND v.isDeleted = 0
";

$stmt = $pdo->query($sql);

$total = 0;
$matched = 0;
$unmatched = 0;
$alreadyText = 0;
$actualChanges = 0;
$updated = 0;
$batch = [];
$started = microtime(true);

while ($row = $stmt->fetch()) {
    $total++;

    $dictionaryPkid = (string)$row['dictionary_pkid'];
    $oldValue = (string)$row['value'];

    if (isset($mapping[$dictionaryPkid][$oldValue])) {
        $matched++;

        $newValue = $mapping[$dictionaryPkid][$oldValue];

        if ($oldValue !== $newValue) {
            $actualChanges++;

            if ($apply) {
                $batch[] = [
                    'pkid' => $row['pkid'],
                    'old' => $oldValue,
                    'new' => $newValue,
                ];

                if (count($batch) >= $batchSize) {
                    $updated += flushBatch($writePdo, $batch);

                    if ($updated > 0) {
                        echo "\rОбновлено: {$updated} / {$actualChanges}";
                    }
                }
            }
        }
    } elseif (preg_match('/^[0-9]{13,16}$/', $oldValue)) {
        $unmatched++;
    } else {
        $alreadyText++;
    }
}

if ($apply && $batch) {
    $updated += flushBatch($writePdo, $batch);
}

$seconds = round(microtime(true) - $started, 2);

echo "\n\nВсего проверено: {$total}\n";
echo "Найдено соответствий: {$matched}\n";
echo "Требовали изменения: {$actualChanges}\n";
echo "Обновлено: {$updated}\n";
echo "Без соответствия: {$unmatched}\n";
echo "Остальные значения: {$alreadyText}\n";
echo "Время: {$seconds} сек.\n";

if (!$apply) {
    echo "\nДанные не изменены. Для обновления используй --apply\n";
} else {
    echo "\nОбновление завершено.\n";
}
