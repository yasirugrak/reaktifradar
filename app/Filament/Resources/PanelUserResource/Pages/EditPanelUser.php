<?php

declare(strict_types=1);

namespace App\Filament\Resources\PanelUserResource\Pages;

use App\Filament\Resources\PanelUserResource;
use Filament\Resources\Pages\EditRecord;

final class EditPanelUser extends EditRecord
{
    protected static string $resource = PanelUserResource::class;
}
