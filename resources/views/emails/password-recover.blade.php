<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; }
        .code-box { 
            font-size: 32px; 
            font-weight: bold; 
            letter-spacing: 5px; 
            color: #4a5568; 
            background: #f7fafc; 
            padding: 20px; 
            text-align: center; 
            border: 2px dashed #cbd5e0;
            margin: 20px 0;
        }
        .footer { font-size: 12px; color: #718096; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Restablecer contraseña</h2>
        <p>Recibimos una solicitud para cambiar tu contraseña. Si no fuiste vos, ignorá este mensaje.</p>

        <div class="code-box">
            {{ $code }}
        </div>

        <p>Este código es válido por 15 minutos e invalida cualquier código anterior.</p>
        
        <div class="footer">
            <p>Este es un mensaje automático, por favor no lo respondas.</p>
        </div>
    </div>
</body>
</html>