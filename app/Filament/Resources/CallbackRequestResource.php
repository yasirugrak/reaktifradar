<?php

namespace App\Filament\Resources;

use App\Access\PanelRole;
use App\Access\RoleGated;
use App\Models\CallbackRequest;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CallbackRequestResource extends Resource
{
    use RoleGated;

    protected static ?string $model = CallbackRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Aranma Talepleri';

    protected static ?string $modelLabel = 'aranma talebi';

    protected static ?string $pluralModelLabel = 'Aranma Talepleri';

    protected static string|UnitEnum|null $navigationGroup = 'ReaktifRadar';

    public static function allowedRoles(): array
    {
        return [PanelRole::SuperAdmin];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return self::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Ad soyad')->disabled(),
            TextInput::make('phone')->label('Telefon')->disabled(),
            TextInput::make('company')->label('İşletme')->disabled(),
            Select::make('status')->label('Durum')->options(self::statuses())->required(),
        ]);
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return ['new' => 'Yeni talep', 'contacted' => 'Görüşüldü', 'closed' => 'Tamamlandı'];
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->label('Ad soyad')->searchable(),
            TextColumn::make('phone')->label('Telefon')->copyable()->searchable(),
            TextColumn::make('company')->label('İşletme')->searchable(),
            TextColumn::make('status')->label('Durum')->badge()->formatStateUsing(fn (string $state) => self::statuses()[$state] ?? $state),
            TextColumn::make('created_at')->label('Talep zamanı')->dateTime('d.m.Y H:i')->sortable(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => CallbackRequestResource\Pages\ListCallbackRequests::route('/')];
    }
}
