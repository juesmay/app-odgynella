{{-- Estilos comunes de los documentos PDF. Requiere $brand. --}}
@page { margin: 104pt 42pt 92pt; }
* { font-family: 'Poppins', 'DejaVu Sans', sans-serif; }
body { color: {{ $brand['ink'] }}; font-size: 9.5pt; line-height: 1.05; }
p, div { line-height: 1.05; }
table { width: 100%; border-collapse: collapse; }
.wave-top { position: fixed; top: -104pt; left: -42pt; width: 612pt; height: 96pt; }
.wave-bottom { position: fixed; bottom: -92pt; left: -42pt; width: 612pt; height: 90pt; }
.foot { position: fixed; bottom: -86pt; left: 0; right: 0; color: #FFFFFF; font-size: 8pt; text-align: right; line-height: 1.1; }
.foot b { font-family: 'Poppins'; font-weight: bold; letter-spacing: .6pt; }
.page-top { position: fixed; top: -76pt; left: 0; right: 0; }
.logo { height: 34pt; }
.title { font-family: 'Bebas Neue', 'DejaVu Sans'; font-size: 42pt; line-height: .9; text-align: right; color: {{ $brand['ink'] }}; letter-spacing: .5pt; }
.code { text-align: right; font-size: 9pt; color: {{ $brand['main'] }}; letter-spacing: 1.2pt; }
.label { font-family: 'Poppins'; font-weight: bold; font-size: 8pt; letter-spacing: 1.6pt; text-transform: uppercase; text-decoration: underline; color: {{ $brand['ink'] }}; }
.big { font-size: 15pt; line-height: 1.15; }
.muted { color: #6B5A70; }
.accent { color: {{ $brand['main'] }}; }
.semi { font-family: 'Poppins SemiBold'; }
.b { font-weight: bold; }
.r { text-align: right; }
.c { text-align: center; }
.box { background: {{ $brand['wash'] }}; padding: 9pt 11pt; }
.grid th { background: {{ $brand['soft'] }}; color: #FFFFFF; font-weight: normal; font-size: 8pt; letter-spacing: 1pt; text-transform: uppercase; padding: 7pt 9pt; text-align: left; border: 1.5pt solid #FFFFFF; }
.grid th.c { text-align: center; } .grid th.r { text-align: right; }
.grid td { padding: 7pt 9pt; border: 1pt dashed #BFB2C4; vertical-align: middle; }
.grid td.val { background: {{ $brand['wash'] }}; text-align: right; }
.check { font-family: 'DejaVu Sans'; font-size: 10pt; color: {{ $brand['main'] }}; }
