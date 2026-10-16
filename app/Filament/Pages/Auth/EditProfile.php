<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\AdminActivities\AdminActivityResource;
use App\Models\AdminActivity;
use App\Support\AdminAlerts;
use App\Support\DeviceLabel;
use App\Support\ImageStore;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use SensitiveParameter;

/**
 * Admin profile: Filament's name/email/password/2FA plus a profile photo, per-event notification choices,
 * the admin's active sessions (with "log out other devices") and their last ten logged actions.
 */
class EditProfile extends BaseEditProfile
{
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // Same as the parent page: profile form, then Filament's 2FA management.
            $this->getFormContentComponent(),
            ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
            Section::make('Sesi aktif')->key('sessions')
                ->icon('heroicon-o-computer-desktop')
                ->description('Perangkat yang sedang masuk ke akun ini.')
                ->headerActions([$this->logoutOtherDevicesAction()])
                ->schema([View::make('filament.pages.auth.profile-sessions')->viewData(fn () => ['sessions' => $this->sessionRows()])]),
            Section::make('Aktivitas terakhir Anda')->key('activities')
                ->icon('heroicon-o-clock')
                ->schema([View::make('filament.pages.auth.profile-activities')->viewData(fn () => [
                    'activities' => AdminActivity::where('user_id', $this->getUser()->getKey())->latest('created_at')->latest('id')->limit(10)->get(),
                    'url' => AdminActivityResource::getUrl('index', ['filters' => ['user_id' => ['value' => $this->getUser()->getKey()]]]),
                ])]),
        ]);
    }

    /** The sessions table rows of this admin (database session driver). Never selects the payload. */
    protected function sessionsQuery(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
            ->where('user_id', $this->getUser()->getKey());
    }

    protected function otherSessionsQuery(): Builder
    {
        return $this->sessionsQuery()->where('id', '!=', session()->getId());
    }

    /** @return Collection<int, object> */
    protected function sessionRows(): Collection
    {
        $current = session()->getId();

        return $this->sessionsQuery()->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $row) => (object) [
                'device' => DeviceLabel::from($row->user_agent),
                'icon' => preg_match('/iPhone|Android.*Mobile/', (string) $row->user_agent) ? 'heroicon-o-device-phone-mobile' : 'heroicon-o-computer-desktop',
                'ip_address' => $row->ip_address,
                'last_active' => Carbon::createFromTimestamp($row->last_activity, config('app.timezone'))->diffForHumans(),
                'is_current' => hash_equals($current, (string) $row->id),
            ]);
    }

    public function logoutOtherDevicesAction(): Action
    {
        return Action::make('logoutOtherDevices')
            ->label('Keluar dari perangkat lain')
            ->icon('heroicon-o-arrow-right-start-on-rectangle')
            ->color('danger')
            ->size('sm')
            ->modalHeading('Keluar dari perangkat lain')
            ->modalDescription('Semua sesi lain akun ini akan berakhir. Masukkan kata sandi Anda saat ini untuk melanjutkan.')
            ->modalSubmitActionLabel('Keluarkan')
            ->schema([
                TextInput::make('password')->label('Kata sandi saat ini')
                    ->password()->revealable()->autocomplete('current-password')->required()
                    ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        // Five guesses per minute, so this modal cannot be used to brute-force the password.
                        $key = 'admin-logout-devices:'.$this->getUser()->getKey();
                        if (RateLimiter::tooManyAttempts($key, 5)) {
                            $fail('Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.');

                            return;
                        }
                        if (! is_string($value) || ! Hash::check($value, $this->getUser()->getAuthPassword())) {
                            RateLimiter::hit($key);
                            $fail('Kata sandi tidak cocok.');

                            return;
                        }
                        RateLimiter::clear($key);
                    }]),
            ])
            ->visible(fn (): bool => $this->otherSessionsQuery()->exists())
            ->action(function (array $data): void {
                $guard = Filament::auth();

                // A remember-me cookie elsewhere could start a fresh session (AuthenticateSession only stores the hash
                // when none is there yet), so rotate the remember token first: every old recaller cookie stops matching.
                $guard->getProvider()->updateRememberToken($guard->user(), Str::random(60));

                // Rehashes the password, and when this request carries (or has queued) a recaller cookie, the guard
                // queues a new one built from the new token and hash, so this device stays remembered.
                $guard->logoutOtherDevices($data['password']);

                // Keep this device signed in: AuthenticateSession compares against the hash stored in the session.
                // session() is the same store StartSession puts on the request (and still works in Livewire tests).
                session()->put(['password_hash_'.Filament::getAuthGuard() => $guard->user()->getAuthPassword()]);

                $count = $this->otherSessionsQuery()->delete();

                Notification::make()->success()->title("$count sesi lain dikeluarkan")->send();
            });
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getAvatarFormComponent(),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
            $this->getNotificationPrefsFormComponent(),
        ]);
    }

    protected function getNotificationPrefsFormComponent(): Component
    {
        return Section::make('Notifikasi')
            ->description('Pilih cara Anda diberi tahu untuk tiap kejadian. Email dikirim ke alamat akun ini.')
            ->statePath('notification_prefs')
            ->schema(collect(AdminAlerts::EVENTS)->map(fn (array $event, string $key) => ToggleButtons::make($key)
                ->label($event[0])
                ->options(AdminAlerts::CHANNELS)
                ->icons(['bell' => 'heroicon-o-bell', 'bell_email' => 'heroicon-o-envelope', 'off' => 'heroicon-o-bell-slash'])
                ->colors(['bell' => 'primary', 'bell_email' => 'primary', 'off' => 'gray'])
                ->inline()
                ->required()
                ->in(array_keys(AdminAlerts::CHANNELS))
                ->default('bell'))->values()->all());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getUser();
        $data['notification_prefs'] = collect(AdminAlerts::EVENTS)->map(fn ($event, string $key) => $user->adminAlertChannel($key))->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        // Only known events, only known channels: anything else sent by the client is dropped.
        if (array_key_exists('notification_prefs', $data)) {
            $submitted = (array) $data['notification_prefs'];
            $data['notification_prefs'] = collect(AdminAlerts::EVENTS)
                ->map(fn ($event, string $key) => array_key_exists((string) ($submitted[$key] ?? ''), AdminAlerts::CHANNELS) ? $submitted[$key] : 'bell')
                ->all();
        }

        return $data;
    }

    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_path')->label('Foto profil')
            ->avatar()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(5120)
            ->disk('public')
            ->directory('avatars')
            // A submitted string path must be the photo already on this account, never another file on the disk.
            ->preventFilePathTampering()
            // Re-encode through GD (drops EXIF and anything hidden in the file) instead of storing the upload as is.
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                return ImageStore::fit($file, 'avatars')
                    ?? throw ValidationException::withMessages(['data.avatar_path' => 'Gambar tidak bisa dibaca. Coba file JPG, PNG, atau WebP lain.']);
            });
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $oldAvatar = $record->getAttribute('avatar_path');

        // Not mass assignable on User: set them here so other user forms can never write them.
        if (array_key_exists('avatar_path', $data)) {
            $record->forceFill(['avatar_path' => $data['avatar_path'] ?: null]);
            unset($data['avatar_path']);
        }
        if (array_key_exists('notification_prefs', $data)) {
            $record->forceFill(['notification_prefs' => $data['notification_prefs']]);
            unset($data['notification_prefs']);
        }

        $record = parent::handleRecordUpdate($record, $data);

        // Replaced or removed photo: delete the old file once the change is committed.
        if ($oldAvatar && $oldAvatar !== $record->getAttribute('avatar_path')) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($oldAvatar));
        }

        return $record;
    }
}
