<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PriceDropMail extends Mailable
{

    public $title;
    public $price;
    public $target;
    public $store;

    public function __construct($title,$price,$target,$store)
    {
        $this->title=$title;
        $this->price=$price;
        $this->target=$target;
        $this->store=$store;
    }

}