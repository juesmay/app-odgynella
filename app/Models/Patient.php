<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use BelongsToClinic;

    public const DOC_TYPES = ['C.C.', 'C.E.', 'Pasaporte', 'T.I.', 'R.C.', 'P.P.T.'];

    public const SOURCES = ['Facebook', 'Instagram', 'Google', 'Referido', 'WhatsApp', 'Otro'];

    /** Preguntas de antecedentes (sí/no) y la alerta que muestran cuando la respuesta es sí. */
    public const ANAMNESIS = [
        'alergia' => ['¿Es alérgica a algún medicamento?', 'Alergia a medicamentos'],
        'anticoagulantes' => ['¿Toma anticoagulantes?', 'Toma anticoagulantes'],
        'hipertension' => ['¿Tiene presión alta?', 'Presión alta'],
        'diabetes' => ['¿Tiene diabetes?', 'Diabetes'],
        'embarazo' => ['¿Está en embarazo o lactancia?', 'Embarazo o lactancia'],
        'corazon' => ['¿Tiene alguna enfermedad del corazón?', 'Enfermedad cardíaca'],
        'fuma' => ['¿Fuma?', 'Fumadora'],
        'cirugia' => ['¿Ha tenido cirugías en el último año?', 'Cirugía reciente'],
    ];

    /** Etapas de la persona, de la asesoría virtual al alta. */
    public const STAGES = [
        'prospecto' => ['En cotización', 'c-conf'],
        'agendado' => ['Valoración agendada', 'c-room'],
        'en_tratamiento' => ['En tratamiento', 'c-done'],
        'terminado' => ['Terminado', 'c-sched'],
        'no_continuo' => ['No continuó', 'c-cancel'],
    ];

    public const LOST_REASONS = [
        'Precio',
        'Lo va a pensar o no responde',
        'Eligió otro consultorio',
        'Tiempo o distancia',
        'Otro',
    ];

    protected $fillable = [
        'clinic_id', 'name', 'stage', 'stage_changed_at', 'lost_reason', 'doc_type', 'doc_number',
        'phone', 'phone_key', 'email', 'birth_date', 'city', 'source', 'data_consent_at', 'photo_consent',
        'anamnesis', 'alert_text', 'ghl_contact_id', 'created_by',
        'infectious', 'oncology', 'family_history', 'hereditary_history', 'exam', 'dentition', 'historical', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'data_consent_at' => 'datetime',
            'stage_changed_at' => 'datetime',
            'photo_consent' => 'boolean',
            'historical' => 'boolean',
            'anamnesis' => 'array',
            'infectious' => 'array',
            'oncology' => 'array',
            'family_history' => 'array',
            'exam' => 'array',
        ];
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class)->latest();
    }

    public function teeth(): HasMany
    {
        return $this->hasMany(PatientTooth::class);
    }

    public function evolutions(): HasMany
    {
        return $this->hasMany(Evolution::class)->latest('signed_at')->latest('id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class)->latest('signed_at');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('taken_on')->orderBy('id');
    }

    public function radiologyOrders(): HasMany
    {
        return $this->hasMany(RadiologyOrder::class)->latest('issued_on')->latest('id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /** Planes de tratamiento: cotizaciones aceptadas, la más reciente primero. */
    public function plans(): HasMany
    {
        return $this->hasMany(Quote::class)->where('status', 'aceptada')->latest('issued_on')->latest('id');
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage][0] ?? $this->stage;
    }

    public function stageClass(): string
    {
        return self::STAGES[$this->stage][1] ?? 'c-sched';
    }

    /** Tiene lo necesario para atenderse en persona: documento y autorización de datos. */
    public function isComplete(): bool
    {
        return filled($this->doc_number) && $this->data_consent_at !== null;
    }

    public function moveTo(string $stage, ?string $lostReason = null): void
    {
        $this->update([
            'stage' => $stage,
            'stage_changed_at' => now(),
            'lost_reason' => $stage === 'no_continuo' ? $lostReason : null,
        ]);
    }

    public function documentLabel(): string
    {
        return $this->doc_number ? trim($this->doc_type.' '.$this->doc_number) : 'Sin documento';
    }

    public static function phoneKey(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Dentición que muestra el odontograma: la que eligió la doctora o la sugerida por la edad. */
    public function dentitionShown(): string
    {
        return array_key_exists((string) $this->dentition, \App\Support\Clinical::DENTITIONS)
            ? $this->dentition
            : \App\Support\Clinical::dentitionForAge($this->age());
    }

    public function age(): ?int
    {
        return $this->birth_date?->age;
    }

    /**
     * Alertas en rojo. Las enfermedades infecciosas son un dato muy sensible: la doctora
     * ve cuáles son; el resto del equipo solo ve un aviso genérico.
     *
     * @return list<string>
     */
    public function alerts(?User $viewer = null): array
    {
        $viewer ??= auth()->user();
        $alerts = [];

        if ($this->alert_text) {
            $alerts[] = $this->alert_text;
        }

        foreach (self::ANAMNESIS as $key => [, $label]) {
            if (! empty(($this->anamnesis ?? [])[$key])) {
                // Si ya hay detalle escrito de la alergia, no repetimos la genérica.
                if ($key === 'alergia' && $this->alert_text) {
                    continue;
                }
                $alerts[] = $label;
            }
        }

        if (! empty($this->infectious)) {
            $alerts[] = $viewer?->isDoctor()
                ? implode(', ', $this->infectious)
                : 'Enfermedad infecciosa: ver antecedentes';
        }

        $onco = $this->oncology ?? [];
        if (($onco['status'] ?? 'no') === 'actual') {
            $alerts[] = 'En tratamiento oncológico';
        } elseif (($onco['status'] ?? 'no') === 'previo') {
            $risk = array_intersect($onco['treatments'] ?? [], ['radio_cabeza_cuello', 'bifosfonatos']);
            $alerts[] = $risk ? 'Antecedente oncológico: radioterapia o bifosfonatos' : 'Antecedente oncológico';
        }

        return $alerts;
    }

    public function nextAppointment(): ?Appointment
    {
        return $this->appointments()
            ->whereDate('date', '>=', today())
            ->whereIn('status', Appointment::OPEN_STATUSES)
            ->orderBy('date')->orderBy('start_time')
            ->first();
    }

    /** WhatsApp para mostrar; los pacientes históricos pueden no tenerlo todavía. */
    public function phoneLabel(): string
    {
        return $this->phone ?: 'Sin WhatsApp';
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }
}
