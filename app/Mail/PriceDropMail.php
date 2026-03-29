<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PriceDropMail extends Mailable
{
    public $title;
    public $price;
    public $target;
    public $store;
    public $type;

    public function __construct($title,$price,$target,$store,$type = 'target_price')
    {
        $this->title=$title;
        $this->price=$price;
        $this->target=$target;
        $this->store=$store;
        $this->type=$type;
    }

    public function build()
    {
        $subject = $this->type === 'target_price'
            ? 'Target price hit: ' . $this->title
            : 'Wishlist sale alert: ' . $this->title;

        return $this->subject($subject)
            ->view('email.price_drops');
    }
}
