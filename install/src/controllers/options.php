<?php
$installMode = isset($_POST['installmode']) ? (int) $_POST['installmode'] : 0;

switch ($installMode) {
    case 0:
    case 2:
        $database_collation = $_POST['database_collation'] ?? 'utf8mb4_general_ci';
        $database_charset = substr($database_collation, 0, strpos($database_collation, '_'));
        $_POST['database_connection_charset'] = $database_charset;
        $_SESSION['databaseloginpassword'] = $_POST['databaseloginpassword'];
        $_SESSION['databaseloginname'] = $_POST['databaseloginname'];
        break;
    case 1:
        if (is_file(EVO_CORE_PATH . 'custom/config/database/connections/default.php')) {
            $db_config = include EVO_CORE_PATH . 'custom/config/database/connections/default.php';
        } else {
            $db_config = include EVO_CORE_PATH . 'config/database/connections/default.php';
        }

        $database_collation = $db_config['collation'];
        $database_connection_charset = $db_config['charset'];
        try {
            $dbh = new PDO(installerDatabaseDsn($db_config['driver'], $db_config['host'], $db_config['database'], $db_config['port'] ?? null), $db_config['username'], $db_config['password']);
            if (empty($database_collation) && $db_config['driver'] === 'mysql') {
                $database_collation = $dbh->query("SHOW VARIABLES LIKE 'collation_database'")->fetch(PDO::FETCH_NUM)[1];
            }
        } catch (PDOException $e) {
            echo '<p class="notok">' . $_lang['database_connection_failed'] . '</p>';
            $_POST['installmode'] = 2;
            require __DIR__ . '/connection.php';
            return;
        }
        $database_collation = $database_collation ?: 'utf8mb4_general_ci';
        $database_charset = explode('_', $database_collation)[0];
        $database_connection_charset = $database_connection_charset ?: $database_charset;
        $database_connection_method = $db_config['method'] ?? 'SET NAMES';

        $_POST['database_name'] = $db_config['database'];
        $_POST['tableprefix'] = $db_config['prefix'];
        $_POST['database_connection_charset'] = $database_connection_charset;
        $_POST['database_connection_method'] = $database_connection_method;
        $_POST['databasehost'] = $db_config['host'];
        $_POST['database_type'] = $db_config['driver'];
        $_POST['database_collation'] = $database_collation;
        $_SESSION['databaseloginname'] = $db_config['username'];
        $_SESSION['databaseloginpassword'] = $db_config['password'];
        break;
    default:
        throw new Exception('installmode is undefined');
}

$ph['install_language'] = $install_language;
$ph['manager_language'] = $manager_language;
$ph['installMode'] = $installMode;
$ph['database_name'] = trim($_POST['database_name'], '`');
$ph['tableprefix'] = $_POST['tableprefix'];
$ph['database_type'] = $_POST['database_type'];
$ph['database_collation'] = $_POST['database_collation'];
$ph['database_connection_charset'] = $_POST['database_connection_charset'];
$ph['database_connection_method'] = $_POST['database_connection_method'];
$ph['databasehost'] = $_POST['databasehost'];
$ph['cmsadmin'] = trim($_POST['cmsadmin'] ?? '');
$ph['cmsadminemail'] = trim($_POST['cmsadminemail'] ?? '');
$ph['cmspassword'] = trim($_POST['cmspassword'] ?? '');
$ph['cmspasswordconfirm'] = trim($_POST['cmspasswordconfirm'] ?? '');

$ph['checked'] = isset($_POST['installdata']) && $_POST['installdata'] == '1' ? 'checked' : '';

# load setup information file
include_once dirname(__DIR__) . '/processor/result.php';
$ph['templates'] = getTemplates($moduleTemplates);
$ph['tvs'] = getTVs($moduleTVs);
$ph['chunks'] = getChunks($moduleChunks);
$ph['modules'] = getModules($moduleModules);
$ph['plugins'] = getPlugins($modulePlugins);
$ph['snippets'] = getSnippets($moduleSnippets);

$ph['action'] = ($installMode == 1) ? 'mode' : 'connection';

$tpl = file_get_contents(dirname(__DIR__) . '/template/actions/options.tpl');
$content = parse($tpl, $ph);
echo parse($content, $_lang, '[%', '%]');
