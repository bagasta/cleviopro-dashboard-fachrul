<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'min:3',
                'max:50',
                Rule::unique(User::class, 'nama')->ignore($this->user()->id),
            ],
            'phone_number' => [
                'required',
                'string',
                'regex:/^62\d{8,}$/',
                'max:20',
                Rule::unique(User::class, 'phone_number')->ignore($this->user()->id),
            ],
        ];
    }
}
