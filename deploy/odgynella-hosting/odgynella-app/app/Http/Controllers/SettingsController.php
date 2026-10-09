<?php

namespace App\Http\Controllers;

use App\Models\ConsentTemplate;
use App\Models\Sede;
use App\Models\Service;
use App\Support\Brand;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'services' => Service::orderBy('is_package')->orderBy('name')->get(),
            'sedes' => Sede::orderBy('id')->get(),
            'clinic' => auth()->user()->clinic,
            'templates' => ConsentTemplate::orderBy('name')->get(),
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        Service::create($this->serviceData($request) + ['active' => true]);

        return back()->with('ok', 'Servicio agregado.');
    }

    public function updateService(Request $request, Service $service): RedirectResponse
    {
        $service->update($this->serviceData($request) + ['active' => $request->boolean('active')]);

        return back()->with('ok', 'Guardado: '.$service->name.'.');
    }

    public function schedule(Request $request): RedirectResponse
    {
        $clinic = $request->user()->clinic;
        $sedeIds = Sede::pluck('id')->all();
        $schedule = [];

        foreach (range(0, 6) as $day) {
            $id = (int) $request->input("day.{$day}");
            $schedule[(string) $day] = in_array($id, $sedeIds, true) ? $id : null;
        }

        $clinic->update(['schedule' => $schedule]);

        return back()->with('ok', 'Horario por sede actualizado.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        ConsentTemplate::create($this->templateData($request) + ['active' => true]);

        return back()->with('ok', 'Consentimiento agregado.');
    }

    public function updateTemplate(Request $request, ConsentTemplate $template): RedirectResponse
    {
        $template->update($this->templateData($request) + ['active' => $request->boolean('active')]);

        return back()->with('ok', 'Consentimiento “'.$template->name.'” guardado. Los ya firmados no cambian.');
    }

    /** @return array<string, string> */
    private function templateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:20000'],
        ], [], ['name' => 'nombre', 'body' => 'texto']);
    }

    public function quotes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quote_validity_days' => ['required', 'integer', 'min:1', 'max:365'],
            'payment_options' => ['required', 'string', 'max:255'],
        ], [], ['quote_validity_days' => 'vigencia', 'payment_options' => 'formas de pago']);

        $request->user()->clinic->update($data);

        return back()->with('ok', 'Datos de la cotización guardados.');
    }

    /** Datos de la doctora y su marca para los documentos PDF. */
    public function brand(Request $request): RedirectResponse
    {
        $clinic = $request->user()->clinic;

        $data = $request->validate([
            'doctor_name' => ['required', 'string', 'max:120'],
            'doctor_title' => ['nullable', 'string', 'max:60'],
            'doctor_license' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'instagram' => ['nullable', 'string', 'max:60'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'payment_note' => ['nullable', 'string', 'max:400'],
            'radiology_centers' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:4096'],
            'signature' => ['nullable', 'string'],
        ], [
            'logo.mimes' => 'El logo debe ser PNG o JPG. Si es posible, PNG con fondo transparente.',
            'logo.max' => 'El logo puede pesar máximo 4 MB.',
        ], ['doctor_name' => 'nombre de la doctora', 'doctor_license' => 'registro profesional', 'email' => 'correo', 'brand_color' => 'color']);

        if (filled($data['signature'] ?? null) && ! Brand::isSignature($data['signature'])) {
            return back()->withErrors(['signature' => 'La firma no se pudo leer. Dibújala de nuevo.']);
        }

        $update = collect($data)->except(['logo', 'signature', 'radiology_centers'])->all();
        $update['radiology_centers'] = collect(preg_split('/\R/', (string) ($data['radiology_centers'] ?? '')))
            ->map(fn ($l) => trim($l))->filter()->values()->all();

        if ($request->hasFile('logo')) {
            $ext = strtolower($request->file('logo')->extension()) === 'png' ? 'png' : 'jpg';
            if ($clinic->logo_path) {
                Storage::disk('local')->delete($clinic->logo_path);
            }
            $update['logo_path'] = $request->file('logo')->storeAs('branding/'.$clinic->id, 'logo-'.time().'.'.$ext, 'local');
        }
        if (filled($data['signature'] ?? null)) {
            $update['signature'] = $data['signature'];
        } elseif ($request->boolean('remove_signature')) {
            $update['signature'] = null;
        }

        $clinic->update($update);

        return redirect(route('settings').'#marca')->with('ok', 'Datos de la doctora guardados.');
    }

    public function logo(Request $request): StreamedResponse
    {
        $clinic = $request->user()->clinic;
        abort_unless($clinic->logo_path && Storage::disk('local')->exists($clinic->logo_path), 404);

        return Storage::disk('local')->response($clinic->logo_path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    /** @return array<string, mixed> */
    private function serviceData(Request $request): array
    {
        $request->merge(['price' => Time::parseMoney($request->input('price'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'duration_min' => ['required', 'integer', 'min:10', 'max:480'],
            'price' => ['required', 'integer', 'min:0'],
        ], [], ['name' => 'nombre', 'duration_min' => 'duración', 'price' => 'precio']);

        $data['is_package'] = $request->boolean('is_package');

        return $data;
    }
}
