<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slug', 'title', 'body_html', 'updated_by_admin_user_id'])]
class LegalPage extends Model
{
    public function updatedByAdminUser()
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_user_id');
    }
}
