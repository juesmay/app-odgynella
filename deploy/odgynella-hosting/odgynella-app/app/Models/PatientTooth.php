<?php

namespace App\Models;

use App\Support\Clinical;
use Illuminate\Database\Eloquent\Model;

class PatientTooth extends Model
{
    protected $table = 'patient_teeth';

    protected $fillable = ['patient_id', 'tooth', 'findings', 'note', 'updated_by'];

    protected function casts(): array
    {
        return ['findings' => 'array', 'tooth' => 'integer'];
    }

    /** Hallazgo que pinta cada cara: la caries manda sobre lo demás. @return array<string, string> */
    public function faceColors(): array
    {
        $priority = ['sellante', 'mancha_blanca', 'obturacion', 'fractura', 'caries'];
        $faces = [];
        foreach ($priority as $type) {
            foreach ($this->findings ?? [] as $f) {
                if ($f['type'] === $type) {
                    foreach ($f['surfaces'] ?? [] as $s) {
                        $faces[$s] = $type;
                    }
                }
            }
        }

        return $faces;
    }

    public function has(string $type): bool
    {
        return collect($this->findings ?? [])->contains('type', $type);
    }

    /** @return list<string> */
    public function abbreviations(): array
    {
        return collect($this->findings ?? [])->map(fn ($f) => Clinical::FINDINGS[$f['type']]['abbr'] ?? '?')->unique()->values()->all();
    }
}
