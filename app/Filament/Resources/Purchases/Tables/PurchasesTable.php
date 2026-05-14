<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('PO#')
                    ->sortable(),

                TextColumn::make('supplier.name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('purchase_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('expected_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

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
                    ->options(PurchaseStatus::class),

                SelectFilter::make('payment_type')
                    ->options(PaymentType::class),

                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('purchase_date', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                
                // Complete Purchase Action
                Action::make('complete')
                    ->label('Mark as Received')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Purchase as Received')
                    ->modalDescription('This will update stock levels automatically.')
                    ->visible(fn (Purchase $record) => $record->status === PurchaseStatus::PENDING)
                    ->action(function (Purchase $record) {
                        try {
                            $service = app(PurchaseService::class);
                            $service->completePurchase($record);
                            
                            Notification::make()
                                ->success()
                                ->title('Purchase Completed')
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
                
                // Cancel Purchase Action
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Purchase')
                    ->modalDescription('This will reverse any stock movements if the purchase was completed.')
                    ->visible(fn (Purchase $record) => $record->status !== PurchaseStatus::CANCELLED)
                    ->action(function (Purchase $record) {
                        try {
                            $service = app(PurchaseService::class);
                            $service->cancelPurchase($record);
                            
                            Notification::make()
                                ->success()
                                ->title('Purchase Cancelled')
                                ->body('Stock movements have been reversed.')
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
