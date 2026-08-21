<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Autenticación')</title>
    <style>
        :root {
            --auth-bg: #f3f4f6;
            --auth-card-bg: #ffffff;
            --auth-border: #e5e7eb;
            --auth-text: #1f2937;
            --auth-muted: #6b7280;
            --auth-primary: #4f46e5;
            --auth-primary-hover: #4338ca;
            --auth-danger: #dc2626;
            --auth-danger-bg: #fef2f2;
            --auth-success: #15803d;
            --auth-success-bg: #f0fdf4;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--auth-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--auth-text);
            padding: 24px;
        }

        .auth-card {
            width: 100%;
            max-width: 400px;
            background: var(--auth-card-bg);
            border: 1px solid var(--auth-border);
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .auth-card h1 {
            font-size: 1.375rem;
            margin: 0 0 4px;
        }

        .auth-card .auth-subtitle {
            margin: 0 0 24px;
            color: var(--auth-muted);
            font-size: 0.9rem;
        }

        .auth-field {
            margin-bottom: 16px;
        }

        .auth-field label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .auth-field input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--auth-border);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--auth-text);
            background: #fff;
        }

        .auth-field input:focus {
            outline: none;
            border-color: var(--auth-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        .auth-field .auth-error {
            color: var(--auth-danger);
            font-size: 0.8rem;
            margin-top: 6px;
        }

        .auth-actions {
            margin-top: 24px;
        }

        .auth-button {
            width: 100%;
            padding: 11px 16px;
            border: none;
            border-radius: 8px;
            background: var(--auth-primary);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }

        .auth-button:hover {
            background: var(--auth-primary-hover);
        }

        .auth-links {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }

        .auth-links a {
            color: var(--auth-primary);
            text-decoration: none;
        }

        .auth-links a:hover {
            text-decoration: underline;
        }

        .auth-alert {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }

        .auth-alert--status {
            background: var(--auth-success-bg);
            color: var(--auth-success);
        }

        .auth-alert--errors {
            background: var(--auth-danger-bg);
            color: var(--auth-danger);
        }

        .auth-alert ul {
            margin: 0;
            padding-left: 18px;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="auth-card">
        <h1>@yield('title', 'Autenticación')</h1>
        @hasSection('subtitle')
            <p class="auth-subtitle">@yield('subtitle')</p>
        @endif

        @if (session('status'))
            <div class="auth-alert auth-alert--status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="auth-alert auth-alert--errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
