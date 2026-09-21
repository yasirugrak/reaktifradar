<?php

declare(strict_types=1);

namespace App\Filament\Resources\PanelUserResource\Pages;

use App\Filament\Resources\PanelUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPanelUsers extends ListRecords
{
    protected static string $resource = PanelUserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Kullanıcı ekle')];
    }
}
