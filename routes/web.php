<?php

use App\Services\ThemeService;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('/', function (Request $request) {
    if (admin_setting('app_url') && admin_setting('safe_mode_enable', 0)) {
        if ($request->server('HTTP_HOST') !== parse_url(admin_setting('app_url'))['host']) {
            abort(403);
        }
    }

    $theme = admin_setting('frontend_theme', 'Xboard');
    $themeService = new ThemeService();

    try {
        if (!$themeService->exists($theme)) {
            if ($theme !== 'Xboard') {
                Log::warning('Theme not found, switching to default theme', ['theme' => $theme]);
                $theme = 'Xboard';
                admin_setting(['frontend_theme' => $theme]);
            }
            $themeService->switch($theme);
        }

        if (!$themeService->getThemeViewPath($theme)) {
            throw new Exception('Theme view file does not exist');
        }

        $publicThemePath = public_path('theme/' . $theme);
        if (!File::exists($publicThemePath)) {
            $themePath = $themeService->getThemePath($theme);
            if (!$themePath || !File::copyDirectory($themePath, $publicThemePath)) {
                throw new Exception('Theme initialization failed');
            }
            Log::info('Theme initialized in public directory', ['theme' => $theme]);
        }

        $renderParams = [
            'title' => admin_setting('app_name', 'ProxyBD'),
            'theme' => $theme,
            'version' => app(UpdateService::class)->getCurrentVersion(),
            'description' => admin_setting('app_description', 'BDIX Bypass Service'),
            'logo' => admin_setting('logo'),
            'theme_config' => $themeService->getConfig($theme)
        ];
        return view('theme::' . $theme . '.dashboard', $renderParams);
    } catch (Exception $e) {
        Log::error('Theme rendering failed', [
            'theme' => $theme,
            'error' => $e->getMessage()
        ]);
        abort(500, 'Theme loading failed');
    }
});

// Dynamic config.js route for ProxyBD theme
Route::get('/theme/{theme}/config.js', function ($theme) {
    // Only allow specific themes that support dynamic config
    if (!in_array($theme, ['ProxyBD'])) {
        abort(404, 'Theme does not support dynamic configuration');
    }

    $themeService = new ThemeService();
    if (!$themeService->exists($theme)) {
        abort(404, 'Theme not found');
    }

    try {
        $themeConfig = $themeService->getConfig($theme) ?? [];
        
        // Generate dynamic config.js based on Laravel settings
        $configJs = view('theme::' . $theme . '.config-js', [
            'theme_config' => $themeConfig,
            'theme' => $theme,
            'title' => admin_setting('app_name', 'ProxyBD'),
            'description' => admin_setting('app_description', 'BDIX Bypass Service')
        ])->render();

        return response($configJs, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff'
        ]);
        
    } catch (Exception $e) {
        Log::error('Failed to generate dynamic config.js', [
            'theme' => $theme,
            'error' => $e->getMessage()
        ]);
        
        // Return a basic fallback config
        $fallbackConfig = "window.PB_CONFIG = { PANEL_TYPE: 'Xboard', API_CONFIG: { urlMode: 'auto' } };";
        return response($fallbackConfig, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8'
        ]);
    }
})->where('theme', '[a-zA-Z0-9_-]+');

//TODO:: Compatibility
Route::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))), function () {
    return view('admin', [
        'title' => admin_setting('app_name', 'ProxyBD'),
        'theme_sidebar' => admin_setting('frontend_theme_sidebar', 'light'),
        'theme_header' => admin_setting('frontend_theme_header', 'dark'),
        'theme_color' => admin_setting('frontend_theme_color', 'default'),
        'background_url' => admin_setting('frontend_background_url'),
        'version' => app(UpdateService::class)->getCurrentVersion(),
        'logo' => admin_setting('logo'),
        'secure_path' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))))
    ]);
});

Route::get('/' . (admin_setting('subscribe_path', 's')) . '/{token}', [\App\Http\Controllers\V1\Client\ClientController::class, 'subscribe'])
    ->middleware('client')
    ->name('client.subscribe');