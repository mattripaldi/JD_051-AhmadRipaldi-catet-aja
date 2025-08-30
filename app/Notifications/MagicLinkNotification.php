<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class MagicLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $magicLinkUrl = URL::signedRoute('magic-link.authenticate', [
            'email' => $notifiable->email,
            'token' => $this->token,
        ]);

        return (new MailMessage)
            ->subject('Your Magic Link to Sign In')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Click the button below to sign in to your account. This link will expire in 60 minutes.')
            ->action('Sign In', $magicLinkUrl)
            ->line('If you did not request this magic link, no further action is required.')
            ->salutation('Best regards, ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
