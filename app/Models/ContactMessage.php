<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'company', 'subject', 'message', 'status', 'read_at', 'notes', 'locale', 'ip_hash'])]
class ContactMessage extends Model
{
    use HasFactory;

    protected $hidden = ['ip_hash', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'read_at' => 'datetime',
        ];
    }
}
