<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A short in-app message addressed to one user, shown in the topbar bell
 * until they read it. Used e.g. to tell a user their invoice cancellation
 * request was granted (see CancellationPermissionController::grant()).
 */
class UserMessage extends Model
{
    public const TYPE_CANCELLATION_GRANTED = 'CANCELLATION_GRANTED';

    protected $fillable = ['user_id', 'type', 'title', 'message', 'link', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
