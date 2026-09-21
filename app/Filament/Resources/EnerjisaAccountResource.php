<?php

namespace App\Filament\Resources;

use App\Access\PanelRole;
use App\Access\RoleGated;
use App\Enerjisa\Models\Account;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class EnerjisaAccountResource extends Resource
{
    use RoleGated;

    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Enerjisa Firmaları';

    protected static ?string $pluralModelLabel = 'Enerjisa Firmaları';

    protected static ?string $modelLabel = 'Enerjisa Firmaları';

    protected static string|UnitEnum|null $navigationGroup = 'ReaktifRadar';

    public static function allowedRoles(): array
    {
        return [PanelRole::SuperAdmin];
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Firma adı')->required()->maxLength(160),
            Toggle::make('is_active')->label('Firma açık')->default(true)->helperText('Kapatıldığında tüm firma kullanıcılarının erişimi ve otomatik bildirimleri durur.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Firma')->searchable(),
            IconColumn::make('is_active')->label('Açık')->boolean(),
            TextColumn::make('members_count')->label('Kullanıcı')->counts('members'),
            TextColumn::make('queries_count')->label('Sorgu')->counts('queries'),
            TextColumn::make('connected_at')->label('Son MDM bağlantı testi')->dateTime('d.m.Y H:i')->placeholder('Test edilmedi')->sortable(),
            TextColumn::make('created_at')->label('Kayıt')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([TernaryFilter::make('is_active')->label('Hesap açık')])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => EnerjisaAccountResource\Pages\ListRecords::route('/'),
            'create' => EnerjisaAccountResource\Pages\CreateRecord::route('/create'),
            'edit' => EnerjisaAccountResource\Pages\EditRecord::route('/{record}/edit')];
    }
}
