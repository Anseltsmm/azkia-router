<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'monthly_quota_tokens' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $plain = 'azkia_'.Str::random(48);

        ApiKey::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'monthly_quota_tokens' => $data['monthly_quota_tokens'] ?? null,
            // no_expiry dicentang -> tanpa batas; jika tidak, endOfDay agar key
            // berlaku penuh sampai akhir tanggal yang dipilih user
            'expires_at' => $request->boolean('no_expiry') ? null : $request->date('expires_at')?->endOfDay(),
            // rate_limit_per_minute diatur oleh admin (default 60 dari migration)
        ]);

        return back()->with('created_api_key', $plain);
    }

    public function toggle(ApiKey $apiKey)
    {
        abort_unless($apiKey->user_id === Auth::id(), 403);

        $apiKey->update(['is_active' => ! $apiKey->is_active]);

        return back()->with('success', __('dashboard.flash.key_toggled'));
    }

    public function removeExpiry(ApiKey $apiKey)
    {
        abort_unless($apiKey->user_id === Auth::id(), 403);

        $apiKey->update(['expires_at' => null]);

        return back()->with('success', __('dashboard.flash.key_expiry_removed'));
    }

    public function destroy(ApiKey $apiKey)
    {
        abort_unless($apiKey->user_id === Auth::id(), 403);

        $apiKey->delete();

        return back()->with('success', __('dashboard.flash.key_deleted'));
    }
}
