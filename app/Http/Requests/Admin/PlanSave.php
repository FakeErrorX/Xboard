<?php

namespace App\Http\Requests\Admin;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class PlanSave extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'content' => 'nullable|string',
            'reset_traffic_method' => 'integer|nullable',
            'transfer_enable' => 'integer|required|min:1',
            'prices' => 'nullable|array',
            'prices.*' => 'nullable|numeric|min:0',
            'group_id' => 'integer|nullable',
            'speed_limit' => 'integer|nullable|min:0',
            'device_limit' => 'integer|nullable|min:0',
            'capacity_limit' => 'integer|nullable|min:0',
            'tags' => 'array|nullable',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validatePrices($validator);
        });
    }

    /**
     * Validate price configuration
     */
    protected function validatePrices(Validator $validator): void
    {
        $prices = $this->input('prices', []);
        
        if (empty($prices)) {
            return;
        }

        // Get all valid periods
        $validPeriods = array_keys(Plan::getAvailablePeriods());
        
        foreach ($prices as $period => $price) {
            // Validate if period is valid
            if (!in_array($period, $validPeriods)) {
                $validator->errors()->add(
                    "prices.{$period}", 
                    "Unsupported subscription period: {$period}"
                );
                continue;
            }

            // Price can be null, empty string, or a number greater than 0
            if ($price !== null && $price !== '') {
                // Convert to number for validation
                $numericPrice = is_numeric($price) ? (float) $price : null;
                
                if ($numericPrice === null) {
                    $validator->errors()->add(
                        "prices.{$period}", 
                        "Price must be in numeric format"
                    );
                } elseif ($numericPrice <= 0) {
                    $validator->errors()->add(
                        "prices.{$period}", 
                        "Price must be greater than 0 (if not needed for this period, leave empty or set to null)"
                    );
                }
            }
        }
    }

    /**
     * Process validated data
     */
    protected function passedValidation(): void
    {
        // Clean and format price data
        $prices = $this->input('prices', []);
        $cleanedPrices = [];

        foreach ($prices as $period => $price) {
            // Only keep valid positive prices
            if ($price !== null && $price !== '' && is_numeric($price)) {
                $numericPrice = (float) $price;
                if ($numericPrice > 0) {
                    // Convert to float and keep two decimal places
                    $cleanedPrices[$period] = round($numericPrice, 2);
                }
            }
        }

        // Update price data in request
        $this->merge(['prices' => $cleanedPrices]);
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Plan name cannot be empty',
            'name.max' => 'Plan name cannot exceed 255 characters',
            'transfer_enable.required' => 'Traffic quota cannot be empty',
            'transfer_enable.integer' => 'Traffic quota must be an integer',
            'transfer_enable.min' => 'Traffic quota must be greater than 0',
            'prices.array' => 'Price configuration format error',
            'prices.*.numeric' => 'Price must be a number',
            'prices.*.min' => 'Price cannot be negative',
            'group_id.integer' => 'Permission group ID must be an integer',
            'speed_limit.integer' => 'Speed limit must be an integer',
            'speed_limit.min' => 'Speed limit cannot be negative',
            'device_limit.integer' => 'Device limit must be an integer',
            'device_limit.min' => 'Device limit cannot be negative',
            'capacity_limit.integer' => 'Capacity limit must be an integer',
            'capacity_limit.min' => 'Capacity limit cannot be negative',
            'tags.array' => 'Tag format must be an array',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'data' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()->toArray()
            ], 422)
        );
    }
}
