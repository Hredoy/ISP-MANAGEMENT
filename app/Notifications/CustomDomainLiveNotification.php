<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomDomainLiveNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $domain) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('আপনার ওয়েবসাইট লাইভ হয়েছে ✅')
            ->line("Your custom domain https://{$this->domain} is now live with a free SSL certificate.")
            ->line('DNS propagated, the certificate was issued, and the site is being served over HTTPS.');
    }
}
