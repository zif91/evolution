# Laravel 13: contributing and upstream integration

This is an experimental compatibility branch of **evocms-community/evolution**,
not a separate CMS history or a stable release.

- Upstream: https://github.com/evocms-community/evolution
- Fork: https://github.com/zif91/evolution
- Baseline: `3.1.x`, initially `c53d99214c35c1c8b8d850900c7a1eaa8d1dda12`
- Work branch: `upgrade/illuminate-13`
- Existing fork branches `3.3.x` and `claude/ai-assistant-resources-nHpCo` are unrelated and retained.

## Branch policy

Keep the fork's `3.1.x` as a clean, fast-forward-only mirror of upstream `3.1.x`.
Do not merge the upgrade into that mirror. The review PR targeting the mirror is
for discussion and comparison; leave it open until upstream maintainers agree
on the integration/release branch.

Contribute fixes in short branches based on `upgrade/illuminate-13`, and open PRs
**into that branch in this fork**. After publication, do not rebase, force-push,
or squash the shared upgrade branch. Merge upstream changes into it so both
projects retain common ancestry.

The original fork's default branch is intentionally unchanged. Use the explicit
branch when cloning:

```sh
git clone --branch upgrade/illuminate-13 https://github.com/zif91/evolution.git
```

Then enter `evolution`, add upstream, and run the isolated test setup:

```sh
cd evolution
git remote add upstream https://github.com/evocms-community/evolution.git
python3 tools/setup-local.py
python3 tests/run.py
php -S 127.0.0.1:8133 tools/dev-router.php
```

Setup requires PHP 8.3+, Composer, Python, Git and a local MySQL instance with
`mysql -uroot` access. It creates a dedicated test database and does not reuse an
unrecognised existing installation. For a TCP test database, set
`EVO_TEST_DB_HOST`; `EVO_TEST_DB_ACCOUNT_HOST` controls the test user's host grant.
No database/admin secrets are committed. Setup stores generated credentials in
`.local/credentials.json`. Never publish `.local`, generated logs or SQL dumps.

## Commit layout

1. **Runtime migration:** CMS source changes, dependency declarations/lock and a
   small, licensed local Salo compatibility fork. This is the main reviewable patch.
2. **Tests and documentation:** reproducible local setup, regression tests,
   verification summary, contributor guidance and CI for PHP 8.3/8.4 + MySQL 8.4.
3. **Generated distribution dependencies:** `core/vendor` only. The upstream
   repository already tracks vendor, so its delivery convention is preserved.
   Review this commit separately; regenerate dependencies from Composer rather
   than hand-editing them.

The runtime commit can be tested without the vendor commit by running
`composer install --working-dir=core --no-scripts`, then installing the CMS and
running `php core/artisan package:discover`.

## Bringing upstream forward

Start with a clean worktree. This example assumes the fork remote is `origin`:

```sh
git fetch upstream
git switch 3.1.x
git merge --ff-only upstream/3.1.x
git push origin 3.1.x
git switch upgrade/illuminate-13
git merge --no-ff upstream/3.1.x
# Resolve source changes; run Composer and the regression suite.
git push origin upgrade/illuminate-13
```

Do not blindly pick "ours" or "theirs" for source or `composer.json` conflicts.
Preserve upstream fixes and the Illuminate 13 requirements. Resolve the
manifests first; regenerate the lock only if dependency requirements changed.
Then regenerate `core/vendor` from the agreed lock. Never edit a generated
vendor file as the only copy of a compatibility fix.

The `core/packages/salo` package uses version 1.0.4 **only within this fork**;
it is not an upstream Salo release. See its compatibility README and MIT license.

## If maintainers create a separate release

Preferred: create their release branch from a compatible upstream baseline and
merge `upgrade/illuminate-13`. The shared ancestry preserves attribution and
makes later fixes transferable. The branch need not have the same name.

If maintainers prefer a small cherry-pick series, they can take the runtime and
test commits and regenerate vendor in their release pipeline. This intentionally
changes commit identities: choose one integration strategy and do not later
mix repeated cherry-picks with merges of the same work.

No strategy eliminates conflicts when both sides change the same code. Keeping
the upstream baseline intact, separating generated dependencies and avoiding
rewrites of shared history makes those conflicts bounded and reviewable.

## Acceptance boundaries

See [upgrade notes](../UPGRADE-LARAVEL-13.md) and
[verification summary](../reports/VERIFICATION.md). Local checks cover Evo API,
DocLister, FormLister, manager editing and migration round trips. This does not
certify every extension or production integration. CI is additive evidence;
its GitHub checks must actually pass before claiming those matrix environments
are verified. Do not ship the preview as a stable release solely because this
branch exists.
