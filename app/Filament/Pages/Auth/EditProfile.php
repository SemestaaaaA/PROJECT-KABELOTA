<?php

namespace App\Filament\Pages\Auth;

use App\Support\AdminAlerts;
use App\Support\ImageStore;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use SensitiveParameter;

/** Admin profile: Filament's name/email/password/2FA plus a profile photo and per-event notification choices. */
class EditProfile extends BaseEditProfile
{
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
