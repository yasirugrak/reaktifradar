<?php

namespace App\Filament\Resources\EnerjisaMemberResource\Pages;

use App\Filament\Resources\EnerjisaMemberResource;
use Filament\Actions\CreateAction;

class ListRecords extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = EnerjisaMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Yeni kayıt')];
    }
}
