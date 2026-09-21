<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Access\PanelRole;
use App\Access\RoleGated;
use App\Filament\Resources\PanelUserResource\Pages;
use App\Models\User;
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

final class PanelUserResource extends Resource
{
    use RoleGated;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Panel Kullanıcıları';

    protected static ?string $modelLabel = 'panel kullanıcısı';

    protected static ?string $pluralModelLabel = 'panel kullanıcıları';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    public static function allowedRoles(): array
    {
        return [PanelRole::SuperAdmin];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Ad')->required()->maxLength(255),
            TextInput::make('email')->label('E-posta')->email()->required()->unique(ignoreRecord: true),
            Select::make('role')->label('Rol')->options([PanelRole::SuperAdmin->value => 'Süper yönetici'])->required(),
            TextInput::make('password')->label('Parola')->password()->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->minLength(8),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Ad')->searchable()->sortable(),
            TextColumn::make('email')->label('E-posta')->searchable(),
            TextColumn::make('role')->label('Rol')->badge()
                ->formatStateUsing(fn (PanelRole|string $state): string => $state instanceof PanelRole ? $state->label() : (PanelRole::tryFrom($state)?->label() ?? $state)),
            TextColumn::make('created_at')->label('Oluşturuldu')->dateTime('d.m.Y H:i')->sortable(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPanelUsers::route('/'),
            'create' => Pages\CreatePanelUser::route('/create'),
            'edit' => Pages\EditPanelUser::route('/{record}/edit'),
        ];
    }
}
