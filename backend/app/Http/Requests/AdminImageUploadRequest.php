<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'context' => ['sometimes', 'in:products,brands,categories,shops'],
        ];
    }
}
