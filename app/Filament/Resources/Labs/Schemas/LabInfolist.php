<?php

namespace App\Filament\Resources\Labs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LabInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user_id')
                    ->numeric(),
                TextEntry::make('name'),
                TextEntry::make('container_name'),
                TextEntry::make('ip_address')
                    ->placeholder('-'),
                TextEntry::make('network'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('ssh_port')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('web_port')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('cpu_limit')
                    ->numeric(),
                TextEntry::make('memory_limit')
                    ->numeric(),
                TextEntry::make('storage_limit')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
