<?php

namespace App\Http\Controllers;

use App\Models\SiteTextRevision;
use App\Support\SiteText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Mode CMS: admins edit marketing copy in place on the public pages (see App\Support\SiteText). */
class CmsController extends Controller
{
    public function mode(Request $request): JsonResponse|RedirectResponse
    {
        $on = ! $request->session()->get('cms_edit', false);
        $request->session()->put('cms_edit', $on);

        return $request->expectsJson()
            ? response()->json(['edit' => $on])
            : back()->with('status', $on ? 'Mode edit aktif. Klik teks bergaris oranye untuk mengubahnya.' : 'Mode edit selesai.');
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $this->ensureKey($key);
        $data = $request->validate([
            'html' => ['required', 'string', 'max:20000'],
            'expected' => ['nullable', 'integer', 'min:0'],
        ], ['html.required' => 'Teks tidak boleh kosong.', 'html.max' => 'Teks terlalu panjang.']);

        $row = SiteText::save($key, $data['html'], $request->user(), (int) ($data['expected'] ?? 0));

        return response()->json(['html' => $row->html, 'version' => (int) $row->version]);
    }

    public function history(string $key): JsonResponse
    {
        $this->ensureKey($key);

        $revisions = SiteTextRevision::where('key', $key)->with('user:id,name')->latest('id')->limit(20)->get()
            ->map(fn (SiteTextRevision $revision) => [
                'id' => $revision->id,
                'user' => $revision->user?->name ?? 'Akun terhapus',
                'created_at' => $revision->created_at?->toIso8601String(),
                'when' => $revision->created_at?->locale('id')->translatedFormat('j M Y, H.i'),
                'text' => SiteText::snippet($revision->html),
            ]);

        return response()->json(['revisions' => $revisions]);
    }

    public function restore(Request $request, string $key): JsonResponse
    {
        $this->ensureKey($key);
        $data = $request->validate(['revision' => ['nullable', 'integer']]);

        $row = SiteText::restore($key, isset($data['revision']) ? (int) $data['revision'] : null, $request->user());

        return response()->json(['html' => $row?->html, 'version' => (int) $row?->version]);
    }

    private function ensureKey(string $key): void
    {
        abort_unless(SiteText::isKey($key), 422, 'Teks ini tidak bisa diedit.');
    }
}
