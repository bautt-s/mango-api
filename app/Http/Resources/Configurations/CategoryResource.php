<?php

namespace App\Http\Resources\Configurations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_system' => $this->is_system,

            // Relaciones
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', function () {
                return new CategoryResource($this->parent);
            }),

            'default_account_id' => $this->default_account_id,
            'default_account' => $this->whenLoaded('defaultAccount', function () {
                return new AccountResource($this->defaultAccount);
            }),

            // Hijos (para árbol jerárquico)
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'has_children' => $this->when(
                $this->relationLoaded('children'),
                fn() => $this->children->isNotEmpty()
            ),

            // Metadata útil
            'depth' => $this->when(
                isset($this->depth),
                fn() => $this->depth
            ),

            'path' => $this->when(
                isset($this->path),
                fn() => $this->path
            ),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
