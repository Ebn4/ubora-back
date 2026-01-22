<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LdapUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $username = $this->username ?? ($this->resource['username'] ?? null);
        $email = $this->email ?? ($this->resource['email'] ?? null);
        $displayName = $this->displayName ?? ($this->resource['displayName'] ?? null);
        $cn = $this->cn ?? ($this->resource['cn'] ?? null);

        return [
            'email' => $email,
            'name' => $displayName ?: $cn ?: 'Inconnu', 
            'cuid' => $username,                        
        ];
    }
}
