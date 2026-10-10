# Untracked CE 3.1 CLI installations

The former CLI installer executed migration `up()` methods directly. It left no
`migrations_install` history; repeating an update replayed the destructive user
schema conversion and deleted `user_attributes` rows.

Both installers now use the normal migrator. For an existing database with no
history, `prepare.php` adopts only the fixed 65-migration CE 3.1 baseline from
upstream commit `c53d99214c35c1c8b8d850900c7a1eaa8d1dda12`. This is a compatibility
baseline, not a list generated from the current migrations directory. Each file
must match its recorded SHA-256, every baseline table/column/index must exist,
obsolete user tables must be absent, and any nonempty stored `settings_version`
must identify 3.1. Completed old CLI installs left this version empty, so the
schema fingerprint is required even when a version string is available.

Unknown or incomplete untracked schemas abort before executing a migration or
creating history. Restore a completed backup or establish a verified migration
history before retrying; this does not recover data already lost by an old CLI
update. New migration filenames remain pending and run through the migrator.
Existing recorded histories continue through normal migration bookkeeping.

Regression: `php tests/installer-migrations.php` requires an empty disposable
`evo_install_test_*` database and `EVO_INSTALL_TEST_DATABASE` matching its exact
configured name. It exercises fresh history, legacy adoption, preserved user
attributes, repeated updates, a future migration, and rejected partial/unknown
baselines. Never run this regression on an application database.
