<?php

declare(strict_types=1);

namespace App\Filament\Resources\PanelUserResource\Pages;

use App\Filament\Resources\PanelUserResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePanelUser extends CreateRecord
{
    protected static string $resource = PanelUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['email_verified_at'] = now();

        return $data;
    }
}
