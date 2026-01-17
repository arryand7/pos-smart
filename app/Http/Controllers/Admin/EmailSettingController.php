<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class EmailSettingController extends Controller
{
    public function edit(): View
    {
        $settings = AppSetting::first() ?? new AppSetting();

        return view('admin.settings.email', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_mailer' => ['nullable', 'string', 'in:smtp,sendmail,mailgun,ses,postmark'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,null'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = AppSetting::firstOrCreate([]);
        $settings->update($validated);

        return redirect()->route('admin.settings.email')
            ->with('status', 'Pengaturan email berhasil disimpan.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        try {
            Mail::raw('Ini adalah email percobaan dari SMART.', function ($message) use ($request) {
                $message->to($request->test_email)
                    ->subject('Test Email dari SMART');
            });

            return redirect()->back()
                ->with('status', 'Email percobaan berhasil dikirim ke ' . $request->test_email);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal mengirim email: ' . $e->getMessage());
        }
    }
}
