<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Photo;
use App\Support\Clinical;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Fotos clínicas. Se guardan en una carpeta privada y solo se ven con sesión iniciada. */
class PhotoController extends Controller
{
    public function index(Request $request, Patient $patient): View
    {
        $photos = $patient->photos()->get();
        $a = $photos->firstWhere('id', $request->integer('a'));
        $b = $photos->firstWhere('id', $request->integer('b'));

        return view('patients.photos', [
            'patient' => $patient,
            'tab' => 'fotos',
            'photos' => $photos,
            'compare' => $a && $b ? [$a, $b] : null,
        ]);
    }

    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validate([
            'photos' => ['required', 'array', 'max:12'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'stage' => ['required', Rule::in(array_keys(Clinical::PHOTO_STAGES))],
            'taken_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [
            'photos.required' => 'Elige al menos una foto.',
            'photos.*.image' => 'Uno de los archivos no es una imagen.',
            'photos.*.max' => 'Cada foto puede pesar máximo 12 MB.',
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $file->store('photos/'.$patient->clinic_id.'/'.$patient->id, 'local');
            $patient->photos()->create([
                'path' => $path,
                'stage' => $data['stage'],
                'taken_on' => $data['taken_on'],
                'note' => $data['note'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        $n = count($request->file('photos'));

        return back()->with('ok', $n === 1 ? 'Foto guardada.' : $n.' fotos guardadas.');
    }

    public function file(Patient $patient, Photo $photo): StreamedResponse
    {
        abort_unless($photo->patient_id === $patient->id, 404);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
