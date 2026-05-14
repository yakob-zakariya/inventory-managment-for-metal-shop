<?php

namespace App\Filament\Resources\Accounts\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CapitalTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'capitalTransactions';

    protected static ?string $title = 'Capital Transactions';

    protected static ?string $recordTitleAttribute = 'transaction_date';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options([
                        'deposit' => 'Deposit (Money IN)',
                        'withdrawal' => 'Withdrawal (Money OUT)',
                    ])
                    ->required()
                    ->default('deposit')
                    ->reactive(),

                TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix('ETB')
                    ->label('Amount'),

                DatePicker::make('transaction_date')
                    ->required()
                    ->default(now())
                    ->label('Transaction Date'),

                Select::make('category')
                    ->options([
                        'capital_injection' => 'Capital Injection',
                        'profit_withdrawal' => 'Profit Withdrawal',
                        'salary' => 'Owner Salary',
                        'loan_to_business' => 'Loan to Business',
                        'loan_repayment' => 'Loan Repayment',
                        'other' => 'Other',
                    ])
                    ->label('Category')
                    ->helperText('What is this transaction for?'),

                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull()
                    ->placeholder('Add any additional notes...'),

                Hidden::make('user_id')
                    ->default(Auth::id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->date()
                    ->sortable()
                    ->label('Date'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'deposit' => 'success',
                        'withdrawal' => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('amount')
                    ->money('ETB')
                    ->sortable()
                    ->color(fn ($record): string => $record->type === 'deposit' ? 'success' : 'danger')
                    ->formatStateUsing(fn ($record): string => 
                        ($record->type === 'deposit' ? '+' : '-') . ' ' . number_format($record->amount, 2)
                    ),

                TextColumn::make('category')
                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', ucwords($state, '_')) : '-')
                    ->label('Category'),

                TextColumn::make('notes')
                    ->limit(30)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    }),

                TextColumn::make('user.name')
                    ->label('Recorded By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Transaction')
                    ->icon('heroicon-o-plus'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
