<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
            position: relative;
            height: 60px;
        }

        .logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 220px;
            height: auto;
        }

        .header h1 {
            margin: 10px 0 5px 0;
            font-size: 18px;
            text-transform: uppercase;
        }

        .header p {
            margin: 0;
            color: #666;
            font-size: 10px;
        }

        .section {
            margin-bottom: 25px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 5px;
            border: 1px solid #eee;
        }

        .info-table .label {
            background-color: #f9fafb;
            font-weight: bold;
            width: 30%;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
        }

        .items-table th {
            background-color: #e5e7eb;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            border-bottom: 1px solid #bbb;
        }

        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="{{ public_path('tb.logo BLACK.png') }}" class="logo" alt="Logo">
        <h1>{{ $title }}</h1>
        <p>Documento Generado: {{ date('d/m/Y H:i') }}</p>
    </div>

    <div class="section">
        <table class="info-table">
            <tr>
                <td class="label">Cotización:</td>
                <td>#{{ $cotizacion->id }} ({{ $cotizacion->numero ?? 'N/A' }})</td>
            </tr>
            <tr>
                <td class="label">Vendido A:</td>
                <td>
                    <strong>{{ $cotizacion->cliente->nombre_empresa ?? '---' }}</strong><br>
                    <small>
                        Contacto: {{ $cotizacion->cliente->nombre_contacto ?? 'N/A' }} | 
                        Tel: {{ $cotizacion->cliente->telefono ?? 'N/A' }} | 
                        Email: {{ $cotizacion->cliente->correo ?? 'N/A' }}
                    </small>
                </td>
            </tr>
            <tr>
                <td class="label">Fecha Cotización:</td>
                <td>{{ \Carbon\Carbon::parse($cotizacion->fecha)->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">Ejecutivo Solicitante:</td>
                <td>{{ $ejecutivo }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Productos en la Solicitud</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nombre del Producto</th>
                    <th>Talla</th>
                    <th>Color</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th style="text-align: right;">Precio Unit.</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $totalGeneral = 0; @endphp
                @foreach($items as $item)
                    @php 
                                        $subtotal = ($item->skuProducto->precio_venta ?? 0) * $item->cantidad;
                        $totalGeneral += $subtotal;
                    @endphp
                    <tr>
                        <td><code>{{ $item->sku }}</code></td>
                        <td>{{ $item->skuProducto->nombre }}</td>
                        <td>{{ $item->skuProducto->talla->nombre ?? '-' }}</td>
                        <td>{{ $item->skuProducto->color->nombre ?? '-' }}</td>
                        <td style="text-align: right;">{{ $item->cantidad }}</td>
                        <td style="text-align: right;">${{ number_format($item->skuProducto->precio_venta ?? 0, 0, ',', '.') }}</td>
                        <td style="text-align: right; font-weight: bold;">${{ number_format($subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #f3f4f6;">
                    <td colspan="6" style="text-align: right; font-weight: bold; padding: 10px;">TOTAL GENERAL</td>
                    <td style="text-align: right; font-weight: bold; font-size: 14px; padding: 10px; color: #1d4ed8;">
                        ${{ number_format($totalGeneral, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="section" style="margin-top: 50px;">
        <table width="100%">
            <tr>
                <td width="33%" style="text-align: center;">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">
                        Solicitado Por
                    </div>
                </td>
                <td width="33%" style="text-align: center;">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">
                        V°B° Bodega
                    </div>
                </td>
                <td width="33%" style="text-align: center;">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">
                        Firma Cliente
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Documento de Control Interno - Tienda Bomberos
    </div>
</body>

</html>