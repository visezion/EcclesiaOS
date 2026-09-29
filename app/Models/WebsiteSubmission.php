<?php

namespace App\Models;

use App\Models\Concerns\UsesOpaqueRouteKeys;
use Illuminate\Database\Eloquent\Model;

class WebsiteSubmission extends Model
{
    use UsesOpaqueRouteKeys;

    protected $fillable = ['church_id', 'type', 'name', 'email', 'phone', 'message', 'status', 'assigned_to', 'private_notes'];

    public const STATUSES = ['new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'archived' => 'Archived'];
}
