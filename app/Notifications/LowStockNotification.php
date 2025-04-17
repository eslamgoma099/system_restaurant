<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\App;

class LowStockNotification extends Notification
{
    use Queueable;

    protected $itemName;
    protected $quantity;

    public function __construct($itemName, $quantity)
    {
        $this->itemName = $itemName;
        $this->quantity = $quantity;
    }

    public function via($notifiable)
    {
        return ['mail']; // البريد الإلكتروني فقط
    }

    public function toMail($notifiable)
    {
        $locale = request()->header('Accept-Language', 'en');
        if (in_array($locale, ['en', 'ar'])) {
            App::setLocale($locale);
        }

        $subject = trans('notifications.low_stock_subject');
        $intro = trans('notifications.low_stock_intro', ['item' => $this->itemName]);
        $quantityLine = trans('notifications.low_stock_quantity', ['quantity' => $this->quantity]);
        $actionText = trans('notifications.low_stock_action');
        $outro = trans('notifications.low_stock_outro');

        return (new MailMessage)
                    ->subject($subject)
                    ->line($intro)
                    ->line($quantityLine)
                    ->action($actionText, url('/api/inventory'))
                    ->line($outro);
    }
}