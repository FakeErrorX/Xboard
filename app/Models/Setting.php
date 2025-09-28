<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'v2_settings';
    protected $guarded = [];
    protected $casts = [
        'name' => 'string',
        'value' => 'string',
    ];

    /**
     * Get actual content value
     */
    public function getContentValue()
    {
        $rawValue = $this->attributes['value'] ?? null;
        
        if ($rawValue === null) {
            return null;
        }

        // If already an array, return directly
        if (is_array($rawValue)) {
            return $rawValue;
        }

        // If it's a numeric string, return original value
        if (is_numeric($rawValue) && !preg_match('/[^\d.]/', $rawValue)) {
            return $rawValue;
        }

        // Try to parse JSON
        if (is_string($rawValue)) {
            $decodedValue = json_decode($rawValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedValue;
            }
        }

        return $rawValue;
    }

    /**
     * Compatibility: maintain original value accessor
     */
    public function getValueAttribute($value)
    {
        return $this->getContentValue();
    }

    /**
     * Create or update setting item
     */
    public static function createOrUpdate(string $name, $value): self
    {
        $processedValue = is_array($value) ? json_encode($value) : $value;
        
        return self::updateOrCreate(
            ['name' => $name],
            ['value' => $processedValue]
        );
    }
}
