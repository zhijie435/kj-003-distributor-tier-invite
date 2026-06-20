<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvitationCodeUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'invitation_code_id',
        'user_id',
    ];

    public function invitationCode()
    {
        return $this->belongsTo(InvitationCode::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
