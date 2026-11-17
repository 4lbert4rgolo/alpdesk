<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Etiqueta · {{ $equipamento->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; }
        body { background: #f4f7f6; padding: 24px; color: #111; }
        .acoes { max-width: 340px; margin: 0 auto 16px; display: flex; gap: 8px; }
        .acoes button { flex: 1; padding: 10px; border: 0; border-radius: 6px; background: #0f7a68; color: #fff; font-size: 15px; cursor: pointer; }
        .acoes a { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 6px; text-align: center; color: #333; text-decoration: none; background: #fff; }
        /* Etiqueta de 70 x 35 mm */
        .etiqueta { width: 70mm; height: 35mm; margin: 0 auto; background: #fff; border: 1px dashed #999; padding: 2.5mm; display: flex; gap: 2.5mm; align-items: center; }
        .qr { width: 30mm; height: 30mm; flex: 0 0 30mm; }
        .qr svg, .qr img { width: 100%; height: 100%; }
        .info { min-width: 0; line-height: 1.25; }
        .marca { font-size: 7pt; color: #0f7a68; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
        .patrimonio { font-size: 13pt; font-weight: bold; margin: 1mm 0; word-break: break-all; }
        .nome { font-size: 8pt; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .loja { font-size: 7pt; color: #444; margin-top: 1mm; }
        .aviso { max-width: 340px; margin: 12px auto 0; font-size: 13px; color: #555; text-align: center; }
        @media print {
            @page { size: 70mm 35mm; margin: 0; }
            body { background: #fff; padding: 0; }
            .acoes, .aviso { display: none; }
            .etiqueta { border: 0; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="acoes">
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="{{ route('equipamentos.show', $equipamento) }}">Voltar</a>
    </div>

    <div class="etiqueta">
        <div class="qr" id="qr" data-url="{{ route('equipamentos.show', $equipamento) }}"></div>
        <div class="info">
            <div class="marca">{{ config('app.name') }} · TI</div>
            <div class="patrimonio">{{ $equipamento->asset_tag ?: 'Nº '.$equipamento->id }}</div>
            <div class="nome">{{ $equipamento->name }}</div>
            <div class="loja">{{ $equipamento->store->name ?? 'Sem loja' }}{{ $equipamento->sector ? ' · '.$equipamento->sector->name : '' }}</div>
        </div>
    </div>

    <p class="aviso">O QR Code abre a ficha deste equipamento no AlpDesk (é preciso estar logado e ter acesso aos equipamentos).</p>

    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
    <script>
        (function () {
            var alvo = document.getElementById('qr');
            if (typeof qrcode !== 'function') {
                alvo.textContent = 'Não foi possível gerar o QR Code (sem internet?).';
                return;
            }
            var qr = qrcode(0, 'M');
            qr.addData(alvo.getAttribute('data-url'));
            qr.make();
            alvo.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
        })();
    </script>
</body>
</html>
