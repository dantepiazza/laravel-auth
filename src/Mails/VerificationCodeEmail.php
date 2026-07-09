<?php

namespace DantePiazza\LaravelAuth\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerificationCodeEmail extends Mailable
{
    use Queueable, SerializesModels;

    private const VIEWS = [
        'password.recover' => 'password-recover',
        'email.verify'     => 'email-verification',
    ];

    private const SUBJECTS = [
        'password.recover' => 'Restablecer contraseña',
        'email.verify'     => 'Verificá tu correo electrónico',
    ];

    public function __construct(
        public readonly int $code,
        public readonly string $type,
        public readonly ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (self::SUBJECTS[$this->type] ?? 'Código de verificación') . " [{$this->code}]",
        );
    }

    public function content(): Content
    {
        $view = self::VIEWS[$this->type] ?? $this->type;

        return new Content(
            view: 'laravel-auth::emails.' . $view,
        );
    }
}
