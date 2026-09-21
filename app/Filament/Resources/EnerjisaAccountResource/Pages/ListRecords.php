<?php

namespace App\Filament\Resources\EnerjisaAccountResource\Pages;

use App\Filament\Resources\EnerjisaAccountResource;
use Filament\Actions\CreateAction;

class ListRecords extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = EnerjisaAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Yeni kayıt')];
    }
}
