<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{

    public function index()
    {

        return auth()
            ->user()
            ->notifications()
            ->with("game")
            ->latest()
            ->get();

    }

}