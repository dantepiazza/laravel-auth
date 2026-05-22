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

    public $code;
    public $type;

    public function __construct(
        public readonly int $code,
        public readonly string $type,
        public readonly ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'password.recover'   => 'Restablecer contraseña',
            'email.verification' => 'Verificá tu correo electrónico',
        ];

        return new Envelope(
            subject: ($subjects[$this->type] ?? 'Código de verificación') . " [{$this->code}]",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.'.$this->type,
        );
    }
}