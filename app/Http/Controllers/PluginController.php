<?php

namespace App\Http\Controllers;

use App\Traits\HasPluginConfig;

/**
 * Plugin controller base class
 *
 * Provides common functionality for all plugin controllers
 */
abstract class PluginController extends Controller
{
    use HasPluginConfig;

    /**
     * Check before executing plugin operations
     */
    protected function beforePluginAction(): ?array
    {
        if (!$this->isPluginEnabled()) {
            return [400, 'Plugin not enabled'];
        }
        return null;
    }
}