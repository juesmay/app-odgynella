{{-- Poner en pausa a un paciente en tratamiento. Requiere $patient. --}}
<details class="card">
    <summary style="cursor:pointer;font-weight:700">Poner en pausa</summary>
    <form method="POST" action="{{ route('patients.pause', $patient) }}" class="stack" style="margin-top:12px">
        @csrf
        <p class="small muted">Para cuando deja el tratamiento a medias. Mientras esté en pausa no sale en cartera ni se le registran abonos. Cuando vuelva, sigue con el mismo plan o se le hace una cotización nueva.</p>
        <label class="field" for="pause-reason"><span>Motivo</span>
            <select id="pause-reason" name="reason" class="input" required>
                <option value="">Elige uno</option>
                @foreach (\App\Models\Patient::PAUSE_REASONS as $r)
                    <option>{{ $r }}</option>
                @endforeach
            </select>
        </label>
        <label class="field" for="pause-detail"><span>Detalle (opcional)</span>
            <input id="pause-detail" name="detail" class="input" maxlength="200" placeholder="Ej: vuelve en enero">
        </label>
        <button class="btn">Poner en pausa</button>
    </form>
</details>
