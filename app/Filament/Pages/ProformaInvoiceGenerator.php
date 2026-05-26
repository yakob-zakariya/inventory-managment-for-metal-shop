<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Product;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ProformaInvoiceGenerator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.proforma-invoice-generator';

    protected static string|UnitEnum|null $navigationGroup = 'Transactions';

    protected static ?string $navigationLabel = 'Proforma Invoice';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'include_vat' => false,
            'vat_rate' => 15,
            'items' => [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Customer Information')
                        ->schema([
                            Select::make('customer_id')
                                ->label('Customer')
                                ->options(Customer::pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->live(),

                            TextInput::make('customer_name')
                                ->label('Customer Name (Override)')
                                ->helperText('Leave empty to use selected customer name'),
                        ])
                        ->columns(2),

                    Section::make('Invoice Items')
                        ->schema([
                            Repeater::make('items')
                                ->schema([
                                    Select::make('product_id')
                                        ->label('Product')
                                        ->options(Product::pluck('name', 'id'))
                                        ->searchable()
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            if ($state) {
                                                $product = Product::find($state);
                                                if ($product) {
                                                    $set('unit_price', $product->selling_price);
                                                    $set('unit', $product->unit);
                                                    $set('current_stock', $product->current_stock);
                                                    $set('cost_price', $product->total_cost);

                                                    // Calculate profit
                                                    $quantity = $get('quantity') ?? 1;
                                                    $profit = ($product->selling_price - $product->total_cost) * $quantity;
                                                    $set('profit', $profit);
                                                }
                                            }
                                        }),

                                    TextInput::make('unit')
                                        ->label('Unit')
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->required()
                                        ->default(1)
                                        ->minValue(1)
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            // Recalculate profit when quantity changes
                                            $productId = $get('product_id');
                                            if ($productId && $state) {
                                                $product = Product::find($productId);
                                                if ($product) {
                                                    $unitPrice = $get('unit_price') ?? $product->selling_price;
                                                    $costPrice = $get('cost_price') ?? $product->total_cost;
                                                    $profit = ($unitPrice - $costPrice) * $state;
                                                    $set('profit', $profit);
                                                }
                                            }
                                        })
                                        ->helperText(function (callable $get) {
                                            $stock = $get('current_stock');
                                            if ($stock !== null) {
                                                return "Available stock: {$stock} units";
                                            }

                                            return null;
                                        }),

                                    TextInput::make('unit_price')
                                        ->label('Unit Price')
                                        ->numeric()
                                        ->required()
                                        ->prefix('ETB')
                                        ->live()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            // Recalculate profit when price changes
                                            $quantity = $get('quantity') ?? 1;
                                            $costPrice = $get('cost_price') ?? 0;
                                            if ($state) {
                                                $profit = ($state - $costPrice) * $quantity;
                                                $set('profit', $profit);
                                            }
                                        })
                                        ->helperText(function (callable $get) {
                                            $profit = $get('profit');
                                            if ($profit !== null) {
                                                $color = $profit >= 0 ? 'success' : 'danger';
                                                $formatted = number_format(abs($profit), 2);

                                                return $profit >= 0
                                                    ? "Profit: ETB {$formatted}"
                                                    : "Loss: ETB {$formatted}";
                                            }

                                            return null;
                                        }),

                                    // Hidden fields for calculations (not shown to user, not in PDF)
                                    TextInput::make('current_stock')
                                        ->hidden()
                                        ->dehydrated(false),

                                    TextInput::make('cost_price')
                                        ->hidden()
                                        ->dehydrated(false),

                                    TextInput::make('profit')
                                        ->hidden()
                                        ->dehydrated(false),
                                ])
                                ->columns(4)
                                ->defaultItems(1)
                                ->addActionLabel('Add Item')
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => Product::find($state['product_id'])?->name ?? 'New Item'),
                        ]),

                    Section::make('VAT & Pricing')
                        ->schema([
                            Toggle::make('include_vat')
                                ->label('Include VAT')
                                ->helperText('Enable if your business is VAT registered')
                                ->live(),

                            TextInput::make('vat_rate')
                                ->label('VAT Rate (%)')
                                ->numeric()
                                ->default(15)
                                ->minValue(0)
                                ->maxValue(100)
                                ->visible(fn (callable $get) => $get('include_vat')),
                        ])
                        ->columns(2),
                ])
                    ->livewireSubmitHandler('generatePdf')
                    ->footer([
                        Actions::make([
                            Action::make('generate')
                                ->label('Generate PDF')
                                ->icon('heroicon-o-document-arrow-down')
                                ->submit('generatePdf')
                                ->size('lg'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function generatePdf()
    {
        $data = $this->form->getState();

        // Validate
        if (empty($data['items'])) {
            Notification::make()
                ->title('No items added')
                ->body('Please add at least one item to the invoice.')
                ->danger()
                ->send();

            return;
        }

        // Get customer
        $customer = Customer::find($data['customer_id']);
        $customerName = $data['customer_name'] ?? $customer->name;

        // Calculate totals
        $items = collect($data['items'])->map(function ($item) {
            $product = Product::find($item['product_id']);
            $total = $item['quantity'] * $item['unit_price'];

            return [
                'description' => $product->name,
                'unit' => $item['unit'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $total,
            ];
        });

        $subtotal = $items->sum('total');
        $vatAmount = $data['include_vat'] ? ($subtotal * ($data['vat_rate'] / 100)) : 0;
        $grandTotal = $subtotal + $vatAmount;

        // Prepare data for PDF
        $pdfData = [
            'customer_name' => $customerName,
            'date' => now()->format('d.m.Y'),
            'proforma_number' => 'PI-'.now()->format('Ymd').'-'.str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'items' => $items,
            'subtotal' => $subtotal,
            'vat_rate' => $data['vat_rate'] ?? 0,
            'vat_amount' => $vatAmount,
            'grand_total' => $grandTotal,
            'include_vat' => $data['include_vat'],
        ];

        // Generate PDF
        $pdf = Pdf::loadView('pdf.proforma-invoice', $pdfData);

        // Download
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'proforma-invoice-'.now()->format('Y-m-d-His').'.pdf');
    }
}
