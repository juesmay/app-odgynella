<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\OdontogramFinding;
use App\Models\OrderTemplate;
use App\Models\Sede;
use App\Support\Clinical;
use App\Support\Time;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Configuración que la doctora ajusta con el tiempo: hallazgos propios del odontograma, plantillas de órdenes,
 * categorías de gasto y la tarifa por hora del consultorio en cada sede.
 */
class ClinicalSettingsController extends Controller
{
    public function storeFinding(Request $request): RedirectResponse
    {
        $f = OdontogramFinding::create($this->findingData($request) + ['active' => true]);
        Clinical::forgetFindings();

        return redirect()->to(route('settings').'#hallazgos')->with('ok', 'Hallazgo “'.$f->name.'” agregado al odontograma.');
    }

    public function updateFinding(Request $request, OdontogramFinding $finding): RedirectResponse
    {
        $finding->update($this->findingData($request, $finding) + ['active' => $request->boolean('active')]);
        Clinical::forgetFindings();

        return redirect()->to(route('settings').'#hallazgos')->with('ok', 'Hallazgo “'.$finding->name.'” guardado.');
    }

    /** @return array<string, mixed> */
    private function findingData(Request $request, ?OdontogramFinding $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80',
                Rule::unique('odontogram_findings', 'name')->where('clinic_id', $request->user()->clinic_id)->ignore($current?->id)],
            'abbr' => ['required', 'string', 'max:4'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['name.unique' => 'Ya tienes un hallazgo con ese nombre.'], ['name' => 'nombre', 'abbr' => 'sigla', 'color' => 'color']);

        $taken = collect(Clinical::FINDINGS)->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        if (in_array(mb_strtolower(trim($data['name'])), $taken, true)) {
            throw ValidationException::withMessages(['name' => 'Ese hallazgo ya viene en el odontograma.']);
        }

        return [
            'name' => trim($data['name']),
            'abbr' => trim($data['abbr']),
            'color' => strtoupper($data['color']),
            // Cambiar de "por caras" a "diente completo" dejaría marcas raras en los dientes ya guardados: solo se elige al crear.
            'faces' => $current ? $current->faces : $request->boolean('faces'),
        ];
    }

    public function storeOrderTemplate(Request $request): RedirectResponse
    {
        OrderTemplate::create($this->templateData($request));

        return redirect()->to(route('settings').'#plantillas')->with('ok', 'Plantilla de orden agregada.');
    }

    public function updateOrderTemplate(Request $request, OrderTemplate $template): RedirectResponse
    {
        $template->update($this->templateData($request));

        return redirect()->to(route('settings').'#plantillas')->with('ok', 'Plantilla “'.$template->title.'” guardada.');
    }

    public function destroyOrderTemplate(OrderTemplate $template): RedirectResponse
    {
        $template->delete(); // las órdenes ya hechas guardan su propio texto

        return redirect()->to(route('settings').'#plantillas')->with('ok', 'Plantilla eliminada.');
    }

    /** @return array<string, string> */
    private function templateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], ['title' => 'título', 'body' => 'texto']);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Expense::TYPES))],
            'name' => ['required', 'string', 'max:80',
                Rule::unique('expense_categories', 'name')->where('clinic_id', $request->user()->clinic_id)->where('type', $request->input('type'))],
        ], ['name.unique' => 'Esa categoría ya existe en ese tipo.'], ['type' => 'tipo', 'name' => 'nombre']);

        ExpenseCategory::create(['type' => $data['type'], 'name' => trim($data['name']), 'private' => $request->boolean('private'), 'active' => true]);

        return redirect()->to(route('settings').'#categorias')->with('ok', 'Categoría agregada.');
    }

    public function updateCategory(Request $request, ExpenseCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80',
                Rule::unique('expense_categories', 'name')->where('clinic_id', $request->user()->clinic_id)->where('type', $category->type)->ignore($category->id)],
        ], ['name.unique' => 'Esa categoría ya existe en ese tipo.'], ['name' => 'nombre']);

        $old = $category->name;
        $name = $category->name === ExpenseCategory::ROOM_TIME ? $old : trim($data['name']); // la de horas de consultorio la usa el sistema
        $category->update(['name' => $name, 'private' => $request->boolean('private'), 'active' => $category->name === ExpenseCategory::ROOM_TIME || $request->boolean('active')]);

        // Los gastos ya registrados siguen el nuevo nombre.
        if ($old !== $name) {
            Expense::where('type', $category->type)->where('category', $old)->update(['category' => $name]);
        }

        return redirect()->to(route('settings').'#categorias')->with('ok', 'Categoría “'.$name.'” guardada.');
    }

    /** Tarifa por hora del consultorio en cada sede. Cambia cada semestre: solo afecta las citas que se atiendan desde ahora. */
    public function rates(Request $request): RedirectResponse
    {
        foreach (Sede::all() as $sede) {
            $value = Time::parseMoney($request->input("rate.{$sede->id}", $sede->hourly_rate));
            $sede->update(['hourly_rate' => max(0, min($value, 10000000))]);
        }

        return redirect()->to(route('settings').'#tarifas')->with('ok', 'Tarifas del consultorio guardadas. Aplican a las citas que se atiendan desde hoy.');
    }
}
