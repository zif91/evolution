<?php

/**
 * Older CLI installers ran all 65 CE 3.1 installer migrations without recording
 * them. Only adopt that pinned, fully completed schema, never the current glob:
 * new migrations must remain pending. Unknown/partial schemas require restoring
 * a backup or a verified migration history before upgrading.
 */
function prepareInstallerMigrations(): void
{
    $repository = evo()->make('migration.repository');
    $schema = evo()->make('db')->connection()->getSchemaBuilder();
    if ($repository->repositoryExists() && count($repository->getRan()) > 0) {
        return;
    }

    $manifest = json_decode(file_get_contents(__DIR__ . '/legacy-3.1.json'), true, 512, JSON_THROW_ON_ERROR);
    $hasExistingSchema = false;
    foreach (array_keys($manifest['schema']) as $table) {
        $hasExistingSchema = $hasExistingSchema || $schema->hasTable($table);
    }
    if (!$hasExistingSchema) {
        return; // Fresh installation: the migrator creates and records everything.
    }

    $fail = static function (string $reason): void {
        throw new RuntimeException('Untracked installer migrations: ' . $reason
            . '. No migration was run. Restore a completed CE 3.1 backup or verified migration history before updating.');
    };
    foreach ($manifest['migrations'] as $name => $hash) {
        $file = dirname(__DIR__, 2) . '/stubs/migrations/' . $name . '.php';
        if (!is_file($file) || !hash_equals($hash, hash_file('sha256', $file))) {
            $fail('the pinned CE 3.1 migration baseline does not match this installer');
        }
    }
    foreach ($manifest['schema'] as $table => $definition) {
        if (!$schema->hasTable($table) || !$schema->hasColumns($table, $definition['columns'])) {
            $fail('incomplete CE 3.1 schema at ' . $table);
        }
        foreach ($definition['indexes'] as $index) {
            if (!$schema->hasIndex($table, $index)) {
                $fail('missing CE 3.1 index ' . $table . '.' . $index);
            }
        }
    }
    foreach (['manager_users', 'web_users', 'web_user_attributes', 'web_user_settings'] as $obsolete) {
        if ($schema->hasTable($obsolete)) {
            $fail('an older schema still contains ' . $obsolete);
        }
    }
    $version = (string) evo()->make('db')->table('system_settings')
        ->where('setting_name', 'settings_version')->value('setting_value');
    // Completed legacy CLI installs left this value empty; nonempty versions
    // must explicitly identify CE 3.1 as well as satisfy the complete schema.
    if ($version !== '' && !preg_match('/^3\.1(?:\.|$)/', $version)) {
        $fail('unsupported stored version ' . $version);
    }

    // MySQL DDL implicitly commits: create the repository before its DML transaction.
    if (!$repository->repositoryExists()) {
        $repository->createRepository();
    }
    evo()->make('db')->transaction(static function () use ($repository, $manifest): void {
        foreach (array_keys($manifest['migrations']) as $name) {
            $repository->log($name, 1);
        }
    });
}
