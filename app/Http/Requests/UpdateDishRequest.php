<?php

namespace App\Http\Requests;

/**
 * PUT replaces the whole dish, so it validates exactly like creating one; only the permission differs.
 */
class UpdateDishRequest extends StoreDishRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('dish'));
    }
}
