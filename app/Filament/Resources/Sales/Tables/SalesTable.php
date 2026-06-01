<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\PaymentType;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
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
                    ->money('ETB')
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

                // Generate Receipt Action
                Action::make('receipt')
                    ->label('Receipt')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(function (Sale $record) {
                        // Prepare data for PDF
                        $items = $record->items->map(function ($item) {
                            return [
                                'product_name' => $item->product->name,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'total_price' => $item->total_price,
                            ];
                        });

                        $pdfData = [
                            'customer_name' => $record->customer?->name ?? 'Walk-in Customer',
                            'receipt_number' => 'SR-'.str_pad($record->id, 6, '0', STR_PAD_LEFT),
                            'sale_date' => $record->sale_date->format('d.m.Y'),
                            'status' => $record->status->label(),
                            'items' => $items,
                            'total_amount' => $record->total_amount,
                            'payment_type' => $record->payment_type->label(),
                            'payment_method' => $record->payment?->payment_method?->label(),
                            'payment_account' => $record->payment?->account?->name,
                        ];

                        // Generate PDF
                        $pdf = Pdf::loadView('pdf.sales-receipt', $pdfData);

                        // Download
                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'sales-receipt-'.$record->id.'-'.now()->format('Y-m-d').'.pdf');
                    }),

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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
