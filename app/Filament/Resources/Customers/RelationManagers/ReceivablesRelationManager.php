<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Shared\Schemas\PaymentFormSchema;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class ReceivablesRelationManager extends RelationManager
{
    protected static string $relationship = 'receivables';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Receivables are created automatically from credit sales
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('sale.id')
                    ->label('Sale #')
                    ->url(fn ($record) => $record->sale ? route('filament.admin.resources.sales.edit', ['record' => $record->sale]) : null)
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Original Amount')
                    ->money('ETB')
                    ->sortable(),

                TextColumn::make('remaining_balance')
                    ->label('Remaining Balance')
                    ->money('ETB')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(function ($record): string {
                        if ($record->is_fully_paid) {
                            return 'Paid';
                        }
                        if ($record->is_overdue) {
                            return 'Overdue';
                        }

                        return 'Pending';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Overdue' => 'danger',
                        'Pending' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'overdue' => 'Overdue',
                    ])
                    ->query(function ($query, $state) {
                        if (! $state['value']) {
                            return $query;
                        }

                        return match ($state['value']) {
                            'paid' => $query->where('remaining_balance', '<=', 0),
                            'pending' => $query->where('remaining_balance', '>', 0)
                                ->where(function ($q) {
                                    $q->whereNull('due_date')
                                        ->orWhere('due_date', '>=', now());
                                }),
                            'overdue' => $query->where('remaining_balance', '>', 0)
                                ->where('due_date', '<', now()),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('due_date', 'asc')
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('recordPayment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->visible(fn ($record) => $record->remaining_balance > 0)
                    ->schema(fn ($record) => PaymentFormSchema::make($record))
                    ->action(function (array $data, $record) {
                        try {
                            app(PaymentService::class)->recordPayment(
                                payableType: get_class($record),
                                payableId: $record->id,
                                accountId: $data['account_id'],
                                amount: $data['amount'],
                                paymentMethod: $data['payment_method'],
                                paymentDate: Carbon::parse($data['payment_date']),
                                notes: $data['notes'] ?? null
                            );

                            Notification::make()
                                ->success()
                                ->title('Payment Recorded')
                                ->body("Payment of {$data['amount']} ETB recorded successfully.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Payment Failed')
                                ->body($e->getMessage())
                                ->send();

                            throw $e;
                        }
                    }),

                Action::make('viewPayments')
                    ->label('View Payments')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Payment History')
                    ->modalContent(function ($record) {
                        $payments = $record->payments()
                            ->with(['account', 'user'])
                            ->orderBy('payment_date', 'desc')
                            ->get();

                        if ($payments->isEmpty()) {
                            return view('filament.components.empty-state', [
                                'message' => 'No payments recorded yet.',
                            ]);
                        }

                        return view('filament.components.payment-history', [
                            'payments' => $payments,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
