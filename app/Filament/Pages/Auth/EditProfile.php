<?php

namespace App\Filament\Pages\Auth;

use App\Support\ImageStore;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use SensitiveParameter;

/** Admin profile: Filament's name/email/password/2FA plus a profile photo. */
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
        ]);
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

        // Not mass assignable on User: set it here so other user forms can never write it.
        if (array_key_exists('avatar_path', $data)) {
            $record->forceFill(['avatar_path' => $data['avatar_path'] ?: null]);
            unset($data['avatar_path']);
        }

        $record = parent::handleRecordUpdate($record, $data);

        // Replaced or removed photo: delete the old file once the change is committed.
        if ($oldAvatar && $oldAvatar !== $record->getAttribute('avatar_path')) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($oldAvatar));
        }

        return $record;
    }
}
