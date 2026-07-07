<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function update(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        Setting::query()->updateOrCreate(['key' => $key], ['value' => ['enabled' => (bool) $data['enabled']]]);

        return back()->with('success', 'Setting updated.');
    }
}
