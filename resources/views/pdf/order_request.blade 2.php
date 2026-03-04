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

        .badge {
            padding: 3px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
            color: white;
        }

        .bg-yellow {
            background-color: #f59e0b;
        }

        .bg-green {
            background-color: #10b981;
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
                <td class="label">ID de Referencia:</td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td class="label">Cotización:</td>
                <td>#{{ $order->cotizacion->id }} ({{ $order->cotizacion->numero ?? 'N/A' }})</td>
            </tr>
            <tr>
                <td class="label">Vendido A:</td>
                <td>
                    <strong>{{ $order->cotizacion->cliente->nombre_empresa ?? '---' }}</strong><br>
                    <small>
                        Contacto: {{ $order->cotizacion->cliente->nombre_contacto ?? 'N/A' }} |
                        Tel: {{ $order->cotizacion->cliente->telefono ?? 'N/A' }} |
                        Email: {{ $order->cotizacion->cliente->correo ?? 'N/A' }}
                    </small>
                </td>
            </tr>
            <tr>
                <td class="label">Estado:</td>
                <td>
                    <span class="badge {{ $order->estado->nombre == 'Pendiente' ? 'bg-yellow' : 'bg-green' }}">
                        {{ $order->estado->nombre }}
                    </span>
                </td>
            </tr>
            <tr>
                <td class="label">Observación:</td>
                <td>{{ $order->observacion ?? 'Sin observaciones.' }}</td>
            </tr>
            <tr>
                <td class="label">Ejecutivo Solicitante:</td>
                <td>{{ $ejecutivo }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Productos Solicitados</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nombre del Producto</th>
                    <th>Talla</th>
                    <th>Color</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th style="text-align: right;">Precio Unit.</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>{{ $order->sku }}</code></td>
                    <td>{{ $order->skuProducto->nombre }}</td>
                    <td>{{ $order->skuProducto->talla->nombre ?? '-' }}</td>
                    <td>{{ $order->skuProducto->color->nombre ?? '-' }}</td>
                    <td style="text-align: right;">{{ $order->cantidad }}</td>
                    <td style="text-align: right;">
                        ${{ number_format($order->skuProducto->precio_venta ?? 0, 0, ',', '.') }}</td>
                    <td style="text-align: right; font-weight: bold;">
                        @php $subtotal = ($order->skuProducto->precio_venta ?? 0) * $order->cantidad; @endphp
                        ${{ number_format($subtotal, 0, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($order->estado->nombre == 'Aceptado')
        <div class="section" style="margin-top: 50px;">
            <table width="100%">
                <tr>
                    <td width="50%" style="text-align: center;">
                        <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto; padding-top: 5px;">
                            Despacho Autorizado Por
                        </div>
                    </td>
                    <td width="50%" style="text-align: center;">
                        <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto; padding-top: 5px;">
                            Recibido Por (Bodega)
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="footer">
        Documento de Control Interno - Tienda Bomberos
    </div>
</body>

</html>