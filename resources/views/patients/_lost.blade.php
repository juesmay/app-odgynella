{{-- Marcar a un prospecto como "no continuó". Requiere $patient. --}}
<details class="card">
    <summary style="cursor:pointer;font-weight:700">No continuó</summary>
    <form method="POST" action="{{ route('patients.lost', $patient) }}" class="stack" style="margin-top:12px">
        @csrf
        <p class="small muted">Sale de la lista de cotizaciones abiertas. Saber el motivo ayuda a mejorar la publicidad y los precios.</p>
        <label class="field" for="reason"><span>Motivo</span>
            <select id="reason" name="reason" class="input" required>
                <option value="">Elige uno</option>
                @foreach (\App\Models\Patient::LOST_REASONS as $r)
                    <option>{{ $r }}</option>
                @endforeach
            </select>
        </label>
        <label class="field" for="detail"><span>Detalle (opcional)</span>
            <input id="detail" name="detail" class="input" maxlength="200">
        </label>
        <button class="btn danger">Marcar como no continuó</button>
    </form>
</details>
