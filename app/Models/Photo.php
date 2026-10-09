<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\Clinical;
use Illuminate\Database\Eloquent\Model;

class Photo extends Model
{
    use BelongsToClinic;

    protected $fillable = ['clinic_id', 'patient_id', 'path', 'stage', 'taken_on', 'note', 'uploaded_by'];

    protected function casts(): array
    {
        return ['taken_on' => 'date'];
    }

    public function stageName(): string
    {
        return Clinical::PHOTO_STAGES[$this->stage] ?? $this->stage;
    }
}
