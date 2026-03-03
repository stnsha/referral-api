<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormCondition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['form_id', 'trigger_form_detail_id'];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function triggerFormDetail(): BelongsTo
    {
        return $this->belongsTo(FormDetails::class, 'trigger_form_detail_id');
    }
}
