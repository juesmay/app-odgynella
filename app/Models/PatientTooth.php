<?php

namespace App\Models;

use App\Support\Clinical;
use Illuminate\Database\Eloquent\Model;

class PatientTooth extends Model
{
    protected $table = 'patient_teeth';

    protected $fillable = ['patient_id', 'tooth', 'findings', 'plan', 'note', 'updated_by'];

    protected function casts(): array
    {
        return ['findings' => 'array', 'plan' => 'array', 'tooth' => 'integer'];
    }

    /**
     * Hallazgo que pinta cada cara: la caries manda sobre lo demás.
     * Los hallazgos propios de la doctora van antes de obturación, fractura y caries.
     *
     * @return array<string, string>
     */
    public function faceColors(): array
    {
        $custom = collect($this->findings ?? [])->pluck('type')->filter(fn ($t) => str_starts_with($t, 'x'))->unique()->all();
        $priority = ['sellante', 'mancha_blanca', ...$custom, 'obturacion', 'fractura', 'caries'];
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

    /** Tratamientos indicados para este diente, en texto. @return list<string> */
    public function planNames(): array
    {
        return collect($this->plan ?? [])->pluck('name')->filter()->values()->all();
    }

    /** @return list<string> */
    public function abbreviations(): array
    {
        return collect($this->findings ?? [])->map(fn ($f) => Clinical::findings()[$f['type']]['abbr'] ?? '?')->unique()->values()->all();
    }
}
