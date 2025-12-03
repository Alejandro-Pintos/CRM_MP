<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #1976d2;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            margin: -30px -30px 30px -30px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .cliente-info {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .cliente-info h3 {
            margin-top: 0;
            color: #1976d2;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        thead {
            background-color: #1976d2;
            color: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            font-weight: bold;
        }
        tbody tr:hover {
            background-color: #f5f5f5;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            background-color: #e3f2fd;
            font-weight: bold;
            font-size: 18px;
        }
        .total-row td {
            color: #1976d2;
            border-top: 2px solid #1976d2;
        }
        .section {
            margin: 20px 0;
        }
        .section h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #777;
            font-size: 12px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>PRESUPUESTO</h1>
            <p>Fecha: {{ \Carbon\Carbon::parse($presupuesto['fecha'])->format('d/m/Y') }}</p>
            <p>Válido hasta: {{ \Carbon\Carbon::parse($presupuesto['fecha_vencimiento'])->format('d/m/Y') }}</p>
        </div>

        <div class="cliente-info">
            <h3>Cliente</h3>
            <p><strong>{{ $cliente['nombre'] }} {{ $cliente['apellido'] }}</strong></p>
            <p>{{ $cliente['email'] }}</p>
            @if(isset($cliente['cuit']) && $cliente['cuit'])
                <p>CUIT: {{ $cliente['cuit'] }}</p>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">Precio Unitario</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($presupuesto['productos'] as $producto)
                <tr>
                    <td>{{ $producto['nombre'] }}</td>
                    <td class="text-center">{{ $producto['cantidad'] }}</td>
                    <td class="text-right">$ {{ number_format($producto['precio_unitario'], 2, ',', '.') }}</td>
                    <td class="text-right">$ {{ number_format($producto['subtotal'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" class="text-right">TOTAL</td>
                    <td class="text-right">$ {{ number_format($presupuesto['total'], 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        @if(isset($presupuesto['condiciones_pago']) && $presupuesto['condiciones_pago'])
        <div class="section">
            <h4>Condiciones de Pago</h4>
            <p>{{ $presupuesto['condiciones_pago'] }}</p>
        </div>
        @endif

        @if(isset($presupuesto['observaciones']) && $presupuesto['observaciones'])
        <div class="section">
            <h4>Observaciones</h4>
            <p>{{ $presupuesto['observaciones'] }}</p>
        </div>
        @endif

        <div class="footer">
            @if(isset($presupuesto['validez']))
            <p>Presupuesto válido por {{ $presupuesto['validez'] }}</p>
            @endif
            <p>Los precios están sujetos a modificación sin previo aviso</p>
            <p style="margin-top: 20px;">Gracias por su confianza</p>
        </div>
    </div>
</body>
</html>
