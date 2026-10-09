# Evolution CE: локальное обновление Illuminate 8 → 13

Проверено 9 октября 2026. Рабочая ветка: `upgrade/illuminate-13`.
Основа: `evocms-community/evolution`, ветка `3.1.x`, commit `c53d99214c35c1c8b8d850900c7a1eaa8d1dda12`, Evolution CE 3.1.31.

CMS использует отдельные компоненты Illuminate, а не полный `laravel/framework`.
Сохранены собственное ядро Evolution, формат БД, менеджер, парсер и API `$modx`.
Установлены Illuminate **13.35.0**, Flysystem 3, Doctrine DBAL 3.10.6, Carbon 3 и Symfony 7.4.
PHP: требуется **8.3+**; фактические проверки выполнены на **8.4.1**, MySQL **9.0.1**.
Composer разрешает зависимости с платформой PHP 8.3.0; это не заменяет запуск тестов на PHP 8.3.

## Результат

- [Тестовый сайт](http://127.0.0.1:8133/).
- [Менеджер](http://127.0.0.1:8133/manager/).
- Учётная запись локального администратора: `.local/credentials.json`. Файл исключён из Git.
- Полный отчёт: [reports/VERIFICATION.md](reports/VERIFICATION.md).
- Изменения опубликованы в отдельной ветке форка `zif91/evolution`. Upstream и production не изменены. См. [правила работы с веткой](docs/LARAVEL13-CONTRIBUTING.md).

## Изменения

1. Обновлены требования Composer и lock-файл; защитные проверки Composer и `roave/security-advisories` сохранены.
2. Расширен контракт приложения: пути с аргументами, debug/maintenance API, termination callbacks.
3. Поддержаны обе формы регистрации провайдера: Laravel `register($provider, $force)` и Evo `register($provider, $options, $force)`. Исправлен поиск уже зарегистрированных провайдеров и вызов их boot callbacks.
4. Исправлены конструкторы консольных команд миграций и публикация каталогов через Flysystem 3 с сохранением поведения `--force`.
5. Для `$modx->doc` и `$modx->user` добавлен fallback на уже поставляемый namespaced MODxAPI. Установленная глобальная реализация имеет приоритет.
6. Salo 1.0.3 ограничивал Illuminate версиями 8–10. Его небольшой MIT-пакет сохранён в `core/packages/salo` как локальный fork 1.0.4, с PHP 8.3 и исправленными путями публикации. Это **не upstream-релиз**; Docker-образ не собирался.
7. Требования PHP в установщике, менеджере и README приведены к 8.3. Убрано обращение к устаревшей константе E_STRICT при загрузке PHP 8.4.

## Повторный запуск этого стенда

Из корня проекта:

```sh
php -S 127.0.0.1:8133 tools/dev-router.php
```

Сервер привязан к loopback. Router блокирует доступ к исходникам ядра, тестам, отчётам и локальным секретам. Его нельзя использовать как production-сервер.

Для **новой локальной копии** с доступным `mysql -uroot`:

```sh
python3 tools/setup-local.py
php -S 127.0.0.1:8133 tools/dev-router.php
```

Setup создаёт только `evolution_lara13_test`, отказывается перезаписывать чужую установку/существующую неизвестную БД, фиксирует версии DocLister/FormLister и создаёт демонстрационную страницу. Повторный запуск setup заново заполняет демонстрационные элементы — не использовать на рабочем сайте.

## Тесты

```sh
python3 tests/run.py
composer validate --working-dir=core --no-check-publish
composer check-platform-reqs --working-dir=core
composer audit --working-dir=core --format=json
```

Интеграционные тесты проверяют точное имя изолированной БД перед работой. Тесты CRUD выполняются в транзакции с rollback. Проверка миграций создаёт временную таблицу и откатывает собственную миграцию; таблица учёта миграций создаётся тестом, если она отсутствует.

`tests/browser.js` — функция для Playwright Page. Перед запуском нужно войти в локальный менеджер. Проверяет frontend, DocLister, FormLister, редактирование через менеджер и отсутствие доступа к редактированию у гостя. Только этот сценарий сохраняет новый заголовок/описание демонстрационной страницы. Снимки сохраняются в `reports/` относительно рабочего каталога Playwright.

## Граница обратной совместимости

Подтверждены перечисленные в отчёте сценарии API Evolution, существующие данные, старый синтаксис сниппетов/чанков/плагинов, DocLister и FormLister. **Совместимость со всеми существующими плагинами не заявляется.**

Для конкретного сайта остаётся проверить его версии Commerce, PageBuilder, платёжные/почтовые интеграции, пользовательские модули и Composer-пакеты. Пакет с требованием `illuminate/*:8.*` не установится с 13 без собственного обновления.

Не эмулируются все внутренности Laravel 8: прямой Flysystem 1 API, удалённые методы `getDoctrine*()` на Illuminate Connection, старые Symfony/Monolog API, прежняя семантика Carbon `diffIn*()` и PHP-функций. Эти обращения нужно адаптировать в использующих их расширениях. Подмена номеров версий или игнорирование зависимостей не применялись.

`doctrine/cache` оставлен для совместимости штатного MODxAPI и помечен Composer как abandoned. Известных security advisories на момент проверки Composer не сообщил.

## Перенос на реальный сайт

1. Сделать отдельную копию файлов и дамп БД; сверить исходную версию CMS и состав дополнений. Эта работа проверяла чистый upstream CE 3.1.31, а не неизвестную production-копию.
2. На staging с PHP 8.3+ применить изменения ядра, Composer-манифест/lock и `core/packages/salo`. Локальные `.local`, `reports`, `tests`, `tools` не публиковать.
3. Выполнить `composer install --working-dir=core --no-dev --no-interaction`, затем `php core/artisan cache:clear-full`.
4. Проверить менеджер, страницы, формы и доставку писем, загрузку файлов, cron, авторизацию и все дополнительные пакеты именно этого сайта.
5. Только после приёмки переносить на production. Для отката вернуть **и файлы, и дамп БД** из одного снимка. Локальный исходный дамп этого стенда: `.local/baseline-laravel8.sql`.

## Источники

- [Evolution CMS Community](https://github.com/evocms-community/evolution)
- [Laravel 9 upgrade](https://laravel.com/docs/9.x/upgrade): переход на Flysystem 3 и другие изменения после 8.
- [Laravel 11 upgrade](https://laravel.com/docs/11.x/upgrade): удаление интеграции Doctrine DBAL из Illuminate.
- [Laravel 12 upgrade](https://laravel.com/docs/12.x/upgrade): обязательный Carbon 3.
- [Laravel 13 upgrade](https://laravel.com/docs/13.x/upgrade): PHP 8.3+, изменения контрактов и поведения.
