<?php

namespace App\Filament\Resources\Affiliates;

use App\Filament\Resources\Affiliates\Pages\CreateAffiliate;
use App\Filament\Resources\Affiliates\Pages\EditAffiliate;
use App\Filament\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Resources\Affiliates\Pages\ViewAffiliate;
use App\Filament\Resources\Affiliates\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\Affiliates\Schemas\AffiliateForm;
use App\Filament\Resources\Affiliates\Schemas\AffiliateInfolist;
use App\Filament\Resources\Affiliates\Tables\AffiliatesTable;
use App\Models\Affiliate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation_groups.affiliate_and_contacts');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.affiliates');
    }

    public static function getModelLabel(): string
    {
        return __('admin.affiliate.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.affiliate.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LeadsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliates::route('/'),
            'create' => CreateAffiliate::route('/create'),
            'view' => ViewAffiliate::route('/{record}'),
            'edit' => EditAffiliate::route('/{record}/edit'),
        ];
    }
}