<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Notifications\ContactReceived;
use App\Support\AdminAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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
        $user = $request->user();

        $rules = [
            'topic' => ['required', Rule::in(self::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'kb_trap' => ['prohibited'],
        ];
        // Signed-in users write as their account; only guests type a name and email.
        if (! $user) {
            $rules += ['name' => ['required', 'string', 'max:80'], 'email' => ['required', 'email', 'max:120']];
        }

        $data = $request->validate($rules, ['kb_trap.prohibited' => 'Pesan tidak bisa dikirim.'], ['name' => 'nama', 'topic' => 'topik', 'message' => 'pesan']);

        $sender = $user
            ? ['user_id' => $user->id, 'name' => $user->isCompany() ? ($user->company?->name ?? $user->name) : $user->name, 'email' => $user->email]
            : ['name' => $data['name'], 'email' => $data['email']];

        $message = ContactMessage::create($sender + ['topic' => $data['topic'], 'message' => $data['message']]);
        Notification::route('mail', config('kabelota.ops_email'))->notify(new ContactReceived($message));
        AdminAlerts::send('contact_message', "Pesan kontak: {$message->topic}", $message->name.': '.Str::limit($message->message, 80), ContactMessageResource::getUrl('index'));

        return redirect()->route('contact')->with('contact_sent', true);
    }
}
