# Illuminate 13: исправлено — на тестах

Ядро из ветки `upgrade/illuminate-13` содержит исправления installer, manager, KCFinder, dev-router и bundled Salo. Его можно скачивать через **Code → Download ZIP**. Это тестовая сборка, ожидающая независимого подтверждения тестерами.

## Внешние пакеты

Обычный ZIP Evolution не включает установленные дополнения. В prerelease `illuminate-13-functional-20261009` приложен `evolution-illuminate13-fixed-packages.zip`: внутри отдельные ZIP **полных исправленных исходников 15 пакетов**, manifest и patch series. Они экспортированы из зафиксированных upstream-клонов, без локальных баз, конфигурации, логинов и тестовых данных. Upstream/Packagist сами по себе ещё не содержат наши изменения.

Для чистой установки устанавливайте нужные пакеты из этих исправленных исходников, следуя README каждого пакета. ZIP исходников Composer-пакета не является автоматическим Extras-установщиком: используйте локальный Composer path repository либо штатный процесс разработки пакета.

Для уже установленной тестовой копии используйте `installed-files.zip` из набора: в нём только изменённые файлы с путями относительно корня Evolution. Сначала сверяйте установленную версию с base в packages.json; сохраните копии заменяемых файлов. Распакуйте overlay **после** обычной установки соответствующих пакетов. Не запускайте composer update поверх overlay: оно вернёт upstream-файлы. Новые исходники module Example включают Translator.php. Затем очистите кеш: `php core/artisan cache:clear-full`.

CBR хранит исполняемый код в БД: простая замена template на диске не обновляет установленный плагин. Для существующего CBR Currency Updater замените его PHP-код через менеджер содержимым `database-plugins/CBR-Currency-Updater.php` из набора; состояние enabled/disabled и настройки оставьте прежними. При чистой установке используйте исправленный install template пакета.

Legacy **clientsettings v2.2.0**: два исправления уже есть в свежем HEAD. Его архив/patch — только backport для v2.2.0; он не включён в общий installed-files.zip. Не накладывайте файлы v2.2.0 поверх v2.2.1.

## Что проверять

PHP8.3/8.4: ZIP fresh install и update/repeat с сохранностью пользователей/ресурсов; native duplicate и picker; формы Save/delete/XLSX; Directory pagination/tree; PageBuilder JSON/RTE/import/massfill; cart/options/checkout/PDF; частичные валютные платежи и повтор callback через собственные тестовые transport fixtures; CBR failure/recovery; Less relative/imported images. Результаты локального прогона: test-results.json. Все issues имеют метку **исправлено — на тестах** и остаются открытыми до независимой проверки.

Manifest packages.json содержит upstream, base/head SHA и SHA256 ZIP/patch. Патчи применяются к соответствующему репозиторию пакета (`git am changes.mbox`), не к репозиторию Evolution.
