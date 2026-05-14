<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\SaleService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Sale#')
                    ->sortable(),

                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->default('Walk-in Customer')
                    ->placeholder('Walk-in'),

                TextColumn::make('sale_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->money()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->total_amount),

                TextColumn::make('payment_type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SaleStatus::class),

                SelectFilter::make('payment_type')
                    ->options(PaymentType::class),

                SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('sale_date', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                
                // Complete Sale Action
                Action::make('complete')
                    ->label('Complete Sale')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Complete Sale')
                    ->modalDescription('This will update stock levels automatically. Stock availability will be checked.')
                    ->visible(fn (Sale $record) => $record->status === SaleStatus::DRAFT)
                    ->action(function (Sale $record) {
                        try {
                            $service = app(SaleService::class);
                            $service->completeSale($record);
                            
                            Notification::make()
                                ->success()
                                ->title('Sale Completed')
                                ->body('Stock has been updated automatically.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                
                // Cancel Sale Action
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Sale')
                    ->modalDescription('This will restore stock if the sale was completed.')
                    ->visible(fn (Sale $record) => $record->status !== SaleStatus::CANCELLED)
                    ->action(function (Sale $record) {
                        try {
                            $service = app(SaleService::class);
                            $service->cancelSale($record);
                            
                            Notification::make()
                                ->success()
                                ->title('Sale Cancelled')
                                ->body('Stock has been restored.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
