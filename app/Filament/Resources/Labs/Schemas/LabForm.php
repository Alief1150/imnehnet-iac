<?php

namespace App\Filament\Resources\Labs\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LabForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('container_name')
                    ->required(),
                TextInput::make('ip_address'),
                TextInput::make('network')
                    ->required()
                    ->default('10.20.0.0/24'),
                Select::make('status')
                    ->options([
                        'PENDING' => 'P e n d i n g',
                        'PROVISIONING' => 'P r o v i s i o n i n g',
                        'READY' => 'R e a d y',
                        'RUNNING' => 'R u n n i n g',
                        'STOPPED' => 'S t o p p e d',
                        'ERROR' => 'E r r o r',
                    ])
                    ->default('PENDING')
                    ->required(),
                TextInput::make('ssh_port')
                    ->numeric(),
                TextInput::make('web_port')
                    ->numeric(),
                TextInput::make('cpu_limit')
                    ->required()
                    ->numeric(),
                TextInput::make('memory_limit')
                    ->required()
                    ->numeric(),
                TextInput::make('storage_limit')
                    ->required()
                    ->numeric(),
            ]);
    }
}
