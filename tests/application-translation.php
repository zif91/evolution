<?php
// No database or web server: exercise the real Core contract with both providers.
error_reporting(E_ALL & ~E_DEPRECATED);
require dirname(__DIR__) . '/core/vendor/autoload.php';
if (!defined('EVO_CORE_PATH')) {
    define('EVO_CORE_PATH', dirname(__DIR__) . '/core/');
}
set_error_handler(static function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

$checks = 0;
foreach ([EvolutionCMS\Providers\TranslationServiceProvider::class,
          Illuminate\Translation\TranslationServiceProvider::class] as $provider) {
    // Skip site bootstrap, but use the actual Core methods and container implementation.
    $app = (new ReflectionClass(EvolutionCMS\Core::class))->newInstanceWithoutConstructor();
    $app->instance('config', new Illuminate\Config\Repository([
        'app' => ['locale' => 'zz', 'fallback_locale' => 'en'],
    ]));
    $app->instance('files', new Illuminate\Filesystem\Filesystem());
    $app->instance('path.lang', EVO_CORE_PATH . 'lang/');
    (new $provider($app))->register();
    $translator = $app->make('translator');
    $translator->addLines(['compat.greeting' => 'Fallback works',
        'validation.required' => 'The :attribute field is required.'], 'en');
    if ($translator->getLocale() !== 'zz' || $translator->getFallback() !== 'en'
        || $translator->get('compat.greeting') !== 'Fallback works') {
        throw new RuntimeException('Configured translation fallback failed: ' . $provider);
    }
    ++$checks;
    // Ensure this is configurable, not a hard-coded English workaround.
    $app['config']->set('app.fallback_locale', 'fr');
    $app->forgetInstance('translator');
    $translator = $app->make('translator');
    $translator->addLines(['compat.greeting' => 'Bonjour',
        'validation.required' => 'Le champ :attribute est requis.'], 'fr');
    if ($translator->get('compat.greeting') !== 'Bonjour') {
        throw new RuntimeException('Custom fallback locale failed: ' . $provider);
    }
    ++$checks;
    $factory = new Illuminate\Validation\Factory($translator, $app);
    $validator = $factory->make([], ['email' => 'required']);
    if (!$validator->fails() || $validator->errors()->first('email') !== 'Le champ email est requis.') {
        throw new RuntimeException('Validation error translation failed: ' . $provider);
    }
    ++$checks;
}
echo "APPLICATION_TRANSLATION_COMPLETE $checks checks\n";
