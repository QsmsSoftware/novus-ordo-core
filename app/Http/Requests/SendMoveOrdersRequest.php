<?php

namespace App\Http\Requests;

use App\Utils\MapsArrayToInstance;
use App\Utils\MapsArrayToObject;
use Illuminate\Foundation\Http\FormRequest;

class SendMoveOrdersRequest extends FormRequest
{
    use MapsArrayToObject;

    public readonly array $orders;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'orders' => 'required|array|min:1',
            'orders.*.division_id' => 'required|integer',
            'orders.*.destination_territory_id' => 'required|integer'
        ];
    }

    protected function passedValidation(): void
    {
        $this->fromArray($this->validated());
    }
}
