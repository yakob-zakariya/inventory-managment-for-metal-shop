<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentMethod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_id')
                    ->relationship('account', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Which account to use for this payment'),

                Select::make('payable_type')
                    ->label('Payment For')
                    ->options([
                        'App\\Models\\Purchase' => 'Purchase',
                        'App\\Models\\Sale' => 'Sale',
                        'App\\Models\\Expense' => 'Expense',
                        'App\\Models\\Payable' => 'Payable (Supplier Credit)',
                        'App\\Models\\Receivable' => 'Receivable (Customer Credit)',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn (callable $set) => $set('payable_id', null)),

                Select::make('payable_id')
                    ->label('Select Transaction')
                    ->options(function (callable $get) {
                        $type = $get('payable_type');
                        if (!$type) return [];

                        return match($type) {
                            'App\\Models\\Purchase' => \App\Models\Purchase::query()
                                ->with('supplier')
                                ->get()
                                ->mapWithKeys(fn($p) => [$p->id => "Purchase #{$p->id} - {$p->supplier->name} - $" . number_format($p->total_amount, 2)]),
                            'App\\Models\\Sale' => \App\Models\Sale::query()
                                ->with('customer')
                                ->get()
                                ->mapWithKeys(fn($s) => [$s->id => "Sale #{$s->id} - " . ($s->customer?->name ?? 'Walk-in') . " - $" . number_format($s->total_amount, 2)]),
                            'App\\Models\\Expense' => \App\Models\Expense::query()
                                ->get()
                                ->mapWithKeys(fn($e) => [$e->id => "{$e->category} - $" . number_format($e->amount, 2) . " - " . $e->expense_date->format('Y-m-d')]),
                            'App\\Models\\Payable' => \App\Models\Payable::query()
                                ->with('supplier')
                                ->where('remaining_balance', '>', 0)
                                ->get()
                                ->mapWithKeys(fn($p) => [$p->id => "Payable #{$p->id} - {$p->supplier->name} - Balance: $" . number_format($p->remaining_balance, 2)]),
                            'App\\Models\\Receivable' => \App\Models\Receivable::query()
                                ->with('customer')
                                ->where('remaining_balance', '>', 0)
                                ->get()
                                ->mapWithKeys(fn($r) => [$r->id => "Receivable #{$r->id} - {$r->customer->name} - Balance: $" . number_format($r->remaining_balance, 2)]),
                            default => [],
                        };
                    })
                    ->required()
                    ->searchable(),

                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0.01)
                    ->helperText('Amount to pay'),

                Select::make('payment_method')
                    ->options(PaymentMethod::class)
                    ->required()
                    ->native(false),

                DatePicker::make('payment_date')
                    ->required()
                    ->default(now())
                    ->maxDate(now()),

                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull()
                    ->placeholder('Optional payment notes'),

                Hidden::make('user_id')
                    ->default(Auth::id()),
            ]);
    }
}
