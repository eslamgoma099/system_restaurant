<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SupplyRequestNotification extends Notification
{
    use Queueable;

    protected $ingredientName;
    protected $quantity;

    public function __construct($ingredientName, $quantity)
    {
        $this->ingredientName = $ingredientName;
        $this->quantity = $quantity;
    }

    public function via($notifiable)
    {
        return ['mail']; // البريد الإلكتروني فقط
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('New Supply Request Created')
                    ->line("A supply request for {$this->quantity} units of {$this->ingredientName} has been created.")
                    ->action('View Requests', url('/api/supply-requests'))
                    ->line('Please review and approve the request.');
    }
}