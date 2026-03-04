<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora de Órdenes de Producción</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        
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

        .header h1 { margin: 10px 0 5px 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 0; color: #666; font-size: 10px; }
        
        .orden-container { margin-bottom: 25px; page-break-inside: avoid; }
        
        .orden-header { 
            background-color: #f3f4f6; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-bottom: none; 
        }
        
        .orden-info { width: 100%; border-collapse: collapse; }
        .orden-info td { border: none; padding: 2px; }
        
        .badge { 
            padding: 3px 6px; 
            border-radius: 4px; 
            font-weight: bold; 
            font-size: 10px; 
            color: white; 
        }
        
        .bg-blue { background-color: #3b82f6; }
        .bg-green { background-color: #10b981; }
        .bg-orange { background-color: #f59e0b; }
        .bg-red { background-color: #ef4444; }
        .bg-purple { background-color: #8b5cf6; }
        .bg-yellow { background-color: #eab308; color: #000; }
        .bg-gray { background-color: #6b7280; }

        .status-history {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
            margin: 10px 0;
        }

        .status-history h4 {
            margin: 0 0 8px 0;
            font-size: 11px;
            font-weight: bold;
            color: #475569;
        }

        .status-timeline {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .status-item {
            background-color: white;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            padding: 4px 6px;
            font-size: 9px;
        }

        .table-detalle { 
            width: 100%; 
            border-collapse: collapse; 
            border: 1px solid #ddd; 
        }
        
        .table-detalle th { 
            background-color: #e5e7eb; 
            padding: 5px; 
            text-align: left; 
            font-size: 10px; 
            border-bottom: 1px solid #bbb; 
        }
        
        .table-detalle td { 
            padding: 5px; 
            border-bottom: 1px solid #eee; 
            font-size: 11px; 
        }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        
        .metadata-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 4px;
            margin-top: 4px;
            font-size: 9px;
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
        
        <h1>Bitácora de Órdenes de Producción</h1>
        <p>Generado el: {{ date('d/m/Y H:i') }}</p>
        @if(isset($filtros) && !empty($filtros))
            <p style="font-size: 9px; margin-top: 5px;">
                Filtros aplicados: {{ implode(' | ', $filtros) }}
            </p>
        @endif
    </div>

    @foreach($ordenes as $orden)
        <div class="orden-container">
            <div class="orden-header">
                <table class="orden-info">
                    <tr>
                        <td width="25%"><strong>Orden:</strong> {{ $orden['codigo'] }}</td>
                        <td width="35%"><strong>Cliente:</strong> {{ $orden['cliente'] }}</td>
                        <td width="20%"><strong>Estado Actual:</strong> 
                            <span class="badge bg-green">{{ $orden['estado_actual'] }}</span>
                        </td>
                        <td width="20%" class="text-right">
                            <strong>Entradas:</strong> {{ $orden['total_entradas'] }}
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Creada:</strong> {{ $orden['fecha_creacion'] }}</td>
                        <td><strong>Última Act.:</strong> {{ $orden['ultima_actualizacion'] }}</td>
                        <td colspan="2">
                            @if(isset($orden['productos']) && !empty($orden['productos']))
                                <strong>Productos:</strong> {{ implode(', ', array_slice($orden['productos'], 0, 2)) }}
                                @if(count($orden['productos']) > 2)
                                    <em>(+{{ count($orden['productos']) - 2 }} más)</em>
                                @endif
                            @endif
                        </td>
                    </tr>
                </table>
            </div>

            @if(isset($orden['historial_estados']) && !empty($orden['historial_estados']))
            <div class="status-history">
                <h4>Historial de Estados</h4>
                <div class="status-timeline">
                    @foreach($orden['historial_estados'] as $estado)
                        <div class="status-item">
                            <strong>{{ $estado['estado'] }}</strong><br>
                            {{ $estado['fecha'] }}
                            @if(isset($estado['duracion']))
                                <br><em>({{ $estado['duracion'] }})</em>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            <table class="table-detalle">
                <thead>
                    <tr>
                        <th width="12%">Fecha/Hora</th>
                        <th width="15%">Usuario</th>
                        <th width="18%">Acción</th>
                        <th width="45%">Detalles</th>
                        <th width="10%">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orden['entradas'] as $entrada)
                        <tr>
                            <td class="font-mono">{{ $entrada['timestamp'] }}</td>
                            <td>{{ $entrada['usuario'] }}</td>
                            <td>
                                @php
                                    $colorClass = 'bg-gray';
                                    switch($entrada['accion']) {
                                        case 'created': $colorClass = 'bg-blue'; break;
                                        case 'status_changed': $colorClass = 'bg-green'; break;
                                        case 'updated': $colorClass = 'bg-orange'; break;
                                        case 'material_consumed': $colorClass = 'bg-red'; break;
                                        case 'material_added': $colorClass = 'bg-purple'; break;
                                        case 'priority_changed': $colorClass = 'bg-yellow'; break;
                                    }
                                @endphp
                                <span class="badge {{ $colorClass }}">{{ $entrada['accion_label'] }}</span>
                            </td>
                            <td>
                                {{ $entrada['detalles'] }}
                                
                                @if(isset($entrada['metadata']) && !empty($entrada['metadata']))
                                    <div class="metadata-box">
                                        @foreach($entrada['metadata'] as $key => $value)
                                            <strong>{{ $key }}:</strong> 
                                            @if(is_array($value))
                                                {{ implode(', ', $value) }}
                                            @else
                                                {{ $value }}
                                            @endif
                                            @if(!$loop->last) | @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($entrada['accion'] === 'status_changed' && isset($entrada['estado_nuevo']))
                                    <span class="badge bg-green">{{ $entrada['estado_nuevo'] }}</span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    @if(empty($ordenes))
        <div style="text-align: center; padding: 50px; color: #666;">
            <h3>No se encontraron registros</h3>
            <p>No hay entradas de bitácora que coincidan con los filtros aplicados.</p>
        </div>
    @endif

    <div class="footer">
        Documento de control interno - Tienda Bomberos - Módulo Fábrica
    </div>
</body>
</html>
