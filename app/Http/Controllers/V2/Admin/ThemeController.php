<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ThemeController extends Controller
{
    private $themeService;

    public function __construct(ThemeService $themeService)
    {
        $this->themeService = $themeService;
    }

    /**
     * Upload new theme
     * 
     * @throws ApiException
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:zip',
                'max:10240', // Maximum 10MB
            ]
        ], [
            'file.required' => 'Please select a theme package file',
            'file.file' => 'Invalid file type',
            'file.mimes' => 'Theme package must be in zip format',
            'file.max' => 'Theme package size cannot exceed 10MB'
        ]);

        try {
            // Check upload directory permissions
            $uploadPath = storage_path('tmp');
            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }

            if (!is_writable($uploadPath)) {
                throw new ApiException('Upload directory has no write permission');
            }

            // Check theme directory permissions
            $themePath = base_path('theme');
            if (!is_writable($themePath)) {
                throw new ApiException('Theme directory has no write permission');
            }

            $file = $request->file('file');

            // Check file MIME type
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, ['application/zip', 'application/x-zip-compressed'])) {
                throw new ApiException('Invalid file type, only ZIP format is supported');
            }

            // Check filename security
            $originalName = $file->getClientOriginalName();
            if (!preg_match('/^[a-zA-Z0-9\-\_\.]+\.zip$/', $originalName)) {
                throw new ApiException('Theme package filename can only contain letters, numbers, underscores, hyphens and dots');
            }

            $this->themeService->upload($file);
            return $this->success(true);

        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Theme upload failed', [
                'error' => $e->getMessage(),
                'file' => $request->file('file')?->getClientOriginalName()
            ]);
            throw new ApiException('Theme upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete theme
     */
    public function delete(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required'
        ]);
        $this->themeService->delete($payload['name']);
        return $this->success(true);
    }

    /**
     * Get all themes and their configuration lists
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getThemes()
    {
        $data = [
            'themes' => $this->themeService->getList(),
            'active' => admin_setting('frontend_theme', 'ProxyBD')
        ];
        return $this->success($data);
    }

    /**
     * Switch theme
     */
    public function switchTheme(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required'
        ]);
        $this->themeService->switch($payload['name']);
        return $this->success(true);
    }

    /**
     * Get theme configuration
     */
    public function getThemeConfig(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required'
        ]);
        $data = $this->themeService->getConfig($payload['name']);
        return $this->success($data);
    }

    /**
     * Save theme configuration
     */
    public function saveThemeConfig(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required',
            'config' => 'required'
        ]);
        $this->themeService->updateConfig($payload['name'], $payload['config']);
        $config = $this->themeService->getConfig($payload['name']);
        return $this->success($config);
    }
}
