<?php

$installMode = (int) ($_POST['installMode'] ?? 0);
$driver = $_POST['method'];
$database = $_POST['database_name'];
$tableprefix = $_POST['tableprefix'];
$collation = $_POST['database_collation'];
$charset = explode('_', $collation)[0];
$output = $_lang['status_checking_database'];

$fail = static function (string $message) use ($output) {
    echo $output . '<span id="database_fail" style="color:#FF0000;">'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</span>';
};
$pass = static function (string $message) use ($output) {
    echo $output . '<span id="database_pass" style="color:#80c000;">' . $message . '</span>';
};

if (!in_array($installMode, [0, 1, 2], true)
    || !preg_match('/^[a-zA-Z0-9_]*$/D', $tableprefix)
    || !preg_match('/^[a-zA-Z0-9_]+$/D', $collation)) {
    $fail($_lang['status_failed']);
    return;
}

try {
    $dbh = new PDO(installerDatabaseDsn($driver, $_POST['host'], $database), $_POST['uid'], $_POST['pwd']);
} catch (PDOException $e) {
    // An upgrade must never silently create a different, empty database.
    $missingDatabase = ($driver === 'mysql' && ($e->errorInfo[1] ?? null) === 1049)
        || ($driver === 'pgsql' && $e->getCode() === '3D000');
    if ($installMode !== 0 || !$missingDatabase) {
        $fail($_lang['status_failed'] . ' ' . $e->getMessage());
        return;
    }
    try {
        $dbh = new PDO(installerDatabaseDsn($driver, $_POST['host']), $_POST['uid'], $_POST['pwd']);
        if ($driver === 'mysql') {
            $quotedDatabase = '`' . str_replace('`', '``', $database) . '`';
            $dbh->exec("CREATE DATABASE {$quotedDatabase} CHARACTER SET {$charset} COLLATE {$collation}");
        } else {
            $quotedDatabase = '"' . str_replace('"', '""', $database) . '"';
            $encoding = $charset === 'utf8mb4' ? 'UTF8' : strtoupper($charset);
            $dbh->exec('CREATE DATABASE ' . $quotedDatabase . ' ENCODING ' . $dbh->quote($encoding));
        }
        $pass($_lang['status_passed_database_created']);
    } catch (PDOException $e) {
        $fail($_lang['status_failed_could_not_create_database'] . ' ' . $e->getMessage());
    }
    return;
}

try {
    if ($driver === 'mysql') {
        $actualCollation = $dbh->query("SHOW VARIABLES LIKE 'collation_database'")->fetch(PDO::FETCH_NUM)[1];
        if ($actualCollation !== $collation && $_POST['database_connection_method'] !== 'SET NAMES') {
            $fail(sprintf($_lang['status_failed_database_collation_does_not_match'], $actualCollation));
            return;
        }
    } else {
        $actualCharset = $dbh->query('SHOW client_encoding')->fetchColumn();
        $expectedCharset = $charset === 'utf8mb4' ? 'UTF8' : strtoupper($charset);
        if (strtoupper($actualCharset) !== $expectedCharset) {
            $fail(sprintf($_lang['status_failed_database_collation_does_not_match'], $actualCharset));
            return;
        }
    }
    try {
        $dbh->query("SELECT 1 FROM {$tableprefix}site_content LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        if (!in_array($e->getCode(), ['42S02', '42P01'], true)) {
            throw $e;
        }
        $tableExists = false;
    }
    if ($installMode === 0 && $tableExists) {
        $fail($_lang['status_failed_table_prefix_already_in_use']);
    } elseif ($installMode !== 0 && !$tableExists) {
        $fail($_lang['table_prefix_not_exist']);
    } else {
        $pass($_lang['status_passed']);
    }
} catch (PDOException $e) {
    $fail($_lang['status_failed'] . ' ' . $e->getMessage());
}
