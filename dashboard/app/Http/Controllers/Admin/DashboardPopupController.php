<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DashboardPopup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardPopupController extends Controller
{
    public function index()
    {
        return view('admin.dashboard-popups', [
            'popups' => DashboardPopup::latest()->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        DashboardPopup::create($this->validated($request));

        return back()->with('success', 'Popup berhasil dibuat.');
    }

    public function update(Request $request, DashboardPopup $dashboardPopup)
    {
        $dashboardPopup->update($this->validated($request));

        return back()->with('success', 'Popup berhasil diperbarui.');
    }

    public function toggle(DashboardPopup $dashboardPopup)
    {
        $dashboardPopup->update(['is_active' => ! $dashboardPopup->is_active]);

        return back()->with('success', 'Status popup berhasil diperbarui.');
    }

    public function destroy(DashboardPopup $dashboardPopup)
    {
        $dashboardPopup->delete();

        return back()->with('success', 'Popup berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['info', 'promo', 'success', 'warning'])],
            'has_shine_effect' => ['sometimes', 'boolean'],
            'button_text' => ['nullable', 'string', 'max:80', 'required_with:button_url'],
            'button_url' => ['nullable', 'url:http,https', 'max:2048', 'required_with:button_text'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['body'] = DashboardPopup::sanitizeBody($data['body']);
        $data['has_shine_effect'] = $request->boolean('has_shine_effect');
        $data['is_active'] = $request->boolean('is_active');
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = filled($data[$field] ?? null)
                ? Carbon::parse($data[$field], 'Asia/Jakarta')->utc()
                : null;
        }

        return $data;
    }
}
