<?php

namespace App\Modules\Identity\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasswordResetOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = is_object($notifiable) && isset($notifiable->name)
            ? (string) $notifiable->name
            : '';

        $expiryMinutes = max(1, (int) config('identity.password_reset.code_ttl_minutes', 15));

        return (new MailMessage)
            ->subject(__('identity::messages.password_reset_email_subject'))
            ->view('identity::emails.password-reset-otp', [
                'name'          => $name,
                'code'          => $this->code,
                'expiryMinutes' => $expiryMinutes,
            ]);
    }
}
