# Обновление существующего сайта: переводчик и смешанный vendor

Дата: 2026-10-10. Основание: сообщение об обновлении существующего сайта, ошибка менеджера `Core::getFallbackLocale() method is undefined` и fatal Composer `Collection::when` / `Enumerable::when`. Доступа к этому серверу не было; причины ниже воспроизведены локально по отдельности.

## 1. Отсутствует getFallbackLocale

Это дефект адаптации нашего приложения к Illuminate 13, а не обязательный признак ошибки базы или обновления. `Illuminate\Translation\TranslationServiceProvider::register()` вызывает `$app->getFallbackLocale()`. Провайдер Evo наследует этот код. В `EvolutionCMS\Traits\Path` был `getLocale()`, но не было `getFallbackLocale()`.

Исправление в `core/src/Traits/Path.php` рядом с `getLocale()`:

```php
public function getFallbackLocale()
{
    return $this['config']->get('app.fallback_locale');
}
```

Метод читает существующую настройку `app.fallback_locale`; он не должен возвращать жёстко заданный язык. Править `core/vendor/illuminate/translation` не требуется. Старые опубликованные ZIP r1/r2 этим коммитом автоматически не обновляются: взять исправленный исходник/патч из ветки или применить указанное изменение к той же версии файла.

## 2. Дубликаты классов и fatal Collection::when

Эта комбинация предупреждений означает, что в каталоге зависимостей одновременно доступны старые и новые реализации:

| Остаток старой поставки | Файл Illuminate 13 |
|---|---|
| `illuminate/support/Reflector.php` | `illuminate/reflection/Reflector.php` |
| `illuminate/support/Traits/ReflectsClosures.php` | `illuminate/reflection/Traits/ReflectsClosures.php` |
| `illuminate/support/Traits/Conditionable.php` | `illuminate/conditionable/Traits/Conditionable.php` |
| `illuminate/collections/HigherOrderWhenProxy.php` | `illuminate/conditionable/HigherOrderWhenProxy.php` |

Распаковка нового архива поверх старого каталога не удаляет исчезнувшие файлы. Если одновременно заменены Composer installed-метаданные, `install` может считать пакет уже установленным. `dump-autoload` пересобирает карту классов, но не удаляет лишние файлы. `exclude-from-classmap` скрывает часть симптомов и не делает смешанный каталог корректной поставкой.

Воспроизведение fatal: загрузка сохранённого старого `Conditionable` перед штатной новой `Collection` даёт точно несовместимые сигнатуры из сообщения пользователя. Это не проверка всего удалённого сайта: остальные его пакеты и доступы неизвестны.

## Восстановление на копии сайта

1. Сохранить файлы, дамп БД, `core/composer.json`, `core/composer.lock`, `core/custom` и конфигурацию приватных репозиториев. Если `composer update` уже изменил lock, сначала сравнить его с согласованным набором пакетов. Не заменять сайтный lock релизным вслепую.
2. Проверить PHP 8.3+ и расширения как в CLI, так и в PHP-FPM/Apache. Восстановить доступ к приватным пакетам: их отсутствие нельзя обходить исключением из зависимостей.
3. Применить исправление `getFallbackLocale()`.
4. Перенести весь старый `core/vendor` в резервный каталог **вне публичного корня сайта**. Не переносить `core/custom`, конфигурацию подключения к БД или пользовательские файлы. Для стандартного набора использовать согласованный lock этой сборки; для кастомного — предварительно подготовленный и проверенный lock с пакетами сайта.
5. Из корня копии сайта выполнить:

```sh
composer install --working-dir=core --no-dev --no-interaction
composer check-platform-reqs --working-dir=core --no-dev
```

`install` использует версии из lock, в отличие от `update`, который разрешает зависимости заново. Это не гарантирует совместимость неизвестных сторонних пакетов. Если команда не завершилась успешно, не продолжать установщик с частично заполненным vendor.

6. После успешной сборки зависимостей выполнить штатное обновление CMS с сохранением БД и данных. Для старых установок ограничения истории миграций описаны в `install/src/migrations/README.md`; неизвестную схему нельзя принудительно помечать обновлённой.
7. Выполнить `php core/artisan cache:clear-full`; если веб-процессы удерживают старый код в OPcache, перезагрузить PHP-FPM/Apache средствами своего хостинга. CLI `opcache_reset()` не сбрасывает кэш другого веб-процесса.
8. Проверить вход и главную менеджера, дерево ресурсов, страницы, формы с ошибками валидации и конкретные дополнения сайта. Успех Composer сам по себе не подтверждает обновление сайта.

Не требуется устанавливать пустую CMS вместо существующего сайта только из-за этих двух ошибок. Они также не доказывают, что все миграции и сторонние дополнения успешно перенесены.

## Логи

В штатной конфигурации Evolution: `core/storage/logs/laravel-YYYY-MM-DD.log` (канал daily) либо `core/storage/logs/laravel.log` (single). Конкретный storage/channel может быть переопределён. Fatal до загрузки приложения нужно искать в выводе CLI и error log PHP-FPM/Apache/панели хостинга; универсального пути к серверному PHP error log нет.

## Проверки исправления

`php tests/application-translation.php` не требует БД: настоящий Core-контейнер и оба настоящих провайдера (Evo/Illuminate) проверяют разрешение переводчика, fallback en/fr и текст ошибки required. На коде до исправления тест падает с `getFallbackLocale() undefined`; после исправления проходит 6 проверок.

`php tests/vendor-integrity.php` до загрузки автолоадера проверяет четыре известных старых файла, затем фактические пути классов и `Collection::when`. Это целевая проверка указанного конфликта, не полный аудит целостности всех пакетов. Оба сценария входят в `python3 tests/run.py` и CI PHP 8.3/8.4.

Источник поведения Composer: https://getcomposer.org/doc/03-cli.md#install-i
