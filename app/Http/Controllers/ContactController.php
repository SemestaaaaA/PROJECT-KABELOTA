<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Notifications\ContactReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public const TOPICS = ['Pertanyaan umum', 'Kendala akun', 'Kerja sama perusahaan', 'Pembayaran lowongan', 'Hapus data saya'];

    public function show(): View
    {
        return view('contact', ['topics' => self::TOPICS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'topic' => ['required', Rule::in(self::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'kb_trap' => ['prohibited'],
        ], ['kb_trap.prohibited' => 'Pesan tidak bisa dikirim.'], ['name' => 'nama', 'topic' => 'topik', 'message' => 'pesan']);

        $message = ContactMessage::create(collect($data)->except('kb_trap')->all());
        Notification::route('mail', config('kabelota.ops_email'))->notify(new ContactReceived($message));

        return redirect()->route('contact')->with('contact_sent', true);
    }
}
