<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Private files the admin reviews from Filament (legal docs, transfer proofs). */
class AdminFileController extends Controller
{
    public function __invoke(Request $request, string $type, int $id)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $path = match ($type) {
            'legalitas' => Company::findOrFail($id)->legal_doc_path,
            'bukti-transfer' => JobPosting::findOrFail($id)->payment_proof_path,
            default => abort(404),
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['X-Robots-Tag' => 'noindex, nofollow']);
    }
}
