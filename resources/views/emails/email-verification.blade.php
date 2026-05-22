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
        <h2>Verificá tu cuenta</h2>
        <p>¡Gracias por sumarte! Para activar tu cuenta, ingresá el siguiente código en la aplicación:</p>

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