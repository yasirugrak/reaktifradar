<?php

namespace App\Filament\Resources;

use App\Access\PanelRole;
use App\Access\RoleGated;
use App\Enerjisa\Models\Member;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class EnerjisaMemberResource extends Resource
{
    use RoleGated;

    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Kullanıcılar';

    protected static ?string $pluralModelLabel = 'Kullanıcılar';

    protected static ?string $modelLabel = 'Kullanıcılar';

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
            TextInput::make('name')->label('Ad soyad')->required()->maxLength(160),
            TextInput::make('email')->label('E-posta')->email()->required()->unique(ignoreRecord: true)->maxLength(255)->dehydrateStateUsing(fn ($state) => mb_strtolower(trim($state))),
            Select::make('account_id')->label('Firma')->relationship('account', 'name')->searchable()->preload()->required()->disabledOn('edit'),
            Toggle::make('is_active')->label('Hesap açık')->default(true),
            TextInput::make('password')->label('Yeni parola')->password()->autocomplete('new-password')->minLength(6)
                ->rules([Password::min(6)->letters()->numbers()])
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                ->helperText('Düzenlemede boş bırakırsanız parola değişmez.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Ad soyad')->searchable(),
            TextColumn::make('email')->label('E-posta')->searchable(),
            TextColumn::make('account.name')->label('Firma')->searchable(),
            IconColumn::make('is_active')->label('Açık')->boolean(),
            TextColumn::make('last_login_at')->label('Son giriş')->dateTime('d.m.Y H:i')->placeholder('Giriş yok')->sortable(),
            TextColumn::make('created_at')->label('Kayıt')->dateTime('d.m.Y H:i')->sortable(),
        ])->filters([TernaryFilter::make('is_active')->label('Hesap açık')])->recordActions([EditAction::make()])->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => EnerjisaMemberResource\Pages\ListRecords::route('/'),
            'create' => EnerjisaMemberResource\Pages\CreateRecord::route('/create'),
            'edit' => EnerjisaMemberResource\Pages\EditRecord::route('/{record}/edit')];
    }
}
