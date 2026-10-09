<tr>
    <td>
        <input type="hidden" name="items[{{ $i }}][service_id]" value="{{ $r['service_id'] ?? '' }}" data-f="service_id">
        <input name="items[{{ $i }}][description]" class="input" value="{{ $r['description'] ?? '' }}" data-f="description" aria-label="Tratamiento" maxlength="200" placeholder="Ej: Carillas en resina">
        <input name="items[{{ $i }}][price_options]" class="input small" style="margin-top:6px;font-size:13px" value="{{ $r['price_options'] ?? '' }}" data-f="price_options" aria-label="Valor según el caso" maxlength="300" placeholder="Valor según el caso (opcional): Uniradicular $400.000, Biradicular $450.000">
    </td>
    <td><input name="items[{{ $i }}][teeth]" class="input" value="{{ $r['teeth'] ?? '' }}" data-f="teeth" aria-label="Dientes" maxlength="60" placeholder="Ej: 13 a 23"></td>
    <td class="r"><input name="items[{{ $i }}][quantity]" class="input" style="text-align:right" inputmode="numeric" value="{{ $r['quantity'] ?? 1 }}" data-f="quantity" aria-label="Cantidad"></td>
    <td class="r"><input name="items[{{ $i }}][unit_price]" class="input" style="text-align:right" inputmode="numeric" value="{{ $r['unit_price'] ?? '' }}" data-f="unit_price" aria-label="Valor unitario" placeholder="0"></td>
    <td class="r money" data-line>$0</td>
    <td><button type="button" class="btn sm ghost danger" data-remove aria-label="Quitar fila">Quitar</button></td>
</tr>
