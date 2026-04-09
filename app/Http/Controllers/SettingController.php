<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::get();
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $settings = Setting::get();

        $settings->update([
            'notification_email' => $request->email,
            'check_interval' => $request->interval,
        ]);

        return back()->with('success', 'Saved');
    }
}
