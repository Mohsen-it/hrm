<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
     * @return array<string, mixed>
     */
    protected function loadTranslations(string $locale): array
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
