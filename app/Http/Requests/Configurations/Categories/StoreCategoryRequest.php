<?php

namespace App\Http\Requests\Configurations\Categories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')
                    ->where('user_id', $this->user()->id)
                    ->where('kind', $this->input('kind', 'expense'))
            ],
            'kind' => [
                'required',
                'string',
                Rule::in(['expense', 'income', 'both']),
            ],
            'color' => [
                'nullable',
                'string',
                'regex:/^#[0-9A-F]{6}$/i',
                'max:9',
            ],
            'icon' => [
                'nullable',
                'string',
                'max:255',
            ],
            'parent_id' => [
                'nullable',
                'uuid',
                'exists:categories,id',
            ],
            'default_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es requerido',
            'name.max' => 'El nombre no puede exceder los 255 caracteres',
            'name.unique' => 'Ya existe una categoría con este nombre y tipo',

            'kind.required' => 'El tipo de categoría es requerido',
            'kind.in' => 'El tipo debe ser: gasto (expense), ingreso (income) o ambos (both)',

            'color.regex' => 'El color debe ser un código hexadecimal válido (ejemplo: #FF5733)',
            'color.max' => 'El color no puede exceder los 9 caracteres',

            'icon.max' => 'El ícono no puede exceder los 255 caracteres',

            'parent_id.uuid' => 'El ID de la categoría padre debe ser un UUID válido',
            'parent_id.exists' => 'La categoría padre especificada no existe',

            'default_account_id.uuid' => 'El ID de la cuenta debe ser un UUID válido',
            'default_account_id.exists' => 'La cuenta especificada no existe o no te pertenece',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'kind' => 'tipo',
            'color' => 'color',
            'icon' => 'ícono',
            'parent_id' => 'categoría padre',
            'default_account_id' => 'cuenta predeterminada',
        ];
    }
}
