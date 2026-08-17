<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;
use Nwidart\Modules\Facades\Module;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';

        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => $user ? $user->getAllPermissions()->pluck('name')->all() : [],
                'roles' => $user ? $user->getRoleNames()->all() : [],
            ],
            'locale' => $locale,
            'direction' => $direction,
            'translations' => $this->loadTranslations($locale),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    /**
     * Load translation messages for the given locale.
     *
     * A corrupted cache blob (e.g. an interrupted write to the database cache)
     * must never take the whole application down: if the cached value cannot be
     * unserialized, the bad key is dropped and the translations are rebuilt.
     *
     * @return array<string, mixed>
     */
    protected function loadTranslations(string $locale): array
    {
        // The cache key embeds a fingerprint of all translation files so that
        // newly added or edited keys are picked up immediately instead of
        // serving a stale snapshot until the 1-hour TTL expires.
        $cacheKey = "inertia:translations:{$locale}:".$this->translationsFingerprint($locale);

        try {
            return Cache::remember($cacheKey, 3600, function () use ($locale) {
                return $this->buildTranslations($locale);
            });
        } catch (\Throwable $exception) {
            Log::warning('Translations cache failure; rebuilding fresh', [
                'locale' => $locale,
                'error' => $exception->getMessage(),
            ]);

            Cache::forget($cacheKey);

            return $this->buildTranslations($locale);
        }
    }

    /**
     * Fingerprint of every translation file for the given locale.
     *
     * Any add/edit/delete of a root or module lang file changes the digest and
     * therefore invalidates the cached translation payload automatically.
     */
    protected function translationsFingerprint(string $locale): string
    {
        $paths = [lang_path($locale)];

        foreach (Module::allEnabled() as $module) {
            $paths[] = $module->getPath()."/lang/{$locale}";
        }

        $mtimes = [];

        foreach ($paths as $path) {
            foreach ((array) glob($path.'/*.php') as $file) {
                $mtimes[] = (int) @filemtime($file);
            }
        }

        return md5(implode(',', $mtimes));
    }

    /**
     * Build the translation payload for the given locale.
     *
     * @return array<string, mixed>
     */
    protected function buildTranslations(string $locale): array
    {
        $paths = [
            lang_path("{$locale}/common.php"),
            lang_path("{$locale}/menu.php"),
            lang_path("{$locale}/dashboard.php"),
            lang_path("{$locale}/components.php"),
            lang_path("{$locale}/permissions.php"),
            lang_path("{$locale}/roles.php"),
            lang_path("{$locale}/actions.php"),
            lang_path("{$locale}/general.php"),
            lang_path("{$locale}/validation.php"),
        ];

        $translations = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $key = basename($path, '.php');
                $translations[$key] = require $path;
            }
        }

        foreach ($this->getModuleLangFiles($locale) as $module => $messages) {
            $translations[$module] = $messages;
        }

        return $translations;
    }

    /**
     * Collect translation files from all enabled modules.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getModuleLangFiles(string $locale): array
    {
        $modules = [];

        foreach (Module::allEnabled() as $module) {
            $moduleName = $module->getLowerName();
            $candidates = [
                $module->getPath()."/lang/{$locale}/{$moduleName}.php",
                $module->getPath()."/lang/{$locale}/".str_replace('_', '', $moduleName).'.php',
                $module->getPath()."/lang/{$locale}/".strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $module->getName())).'.php',
            ];

            foreach ($candidates as $path) {
                if (is_file($path)) {
                    $modules[basename($path, '.php')] = require $path;
                    break;
                }
            }
        }

        return $modules;
    }
}
