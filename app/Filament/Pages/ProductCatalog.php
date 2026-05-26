<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Product;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ProductCatalog extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.product-catalog';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'include_selling_price' => true,
            'include_cost_price' => false,
            'stock_filter' => 'all',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Filter Options')
                        ->schema([
                            Select::make('category_id')
                                ->label('Filter by Category')
                                ->options(Category::pluck('name', 'id'))
                                ->placeholder('All Categories')
                                ->searchable()
                                ->preload(),

                            Select::make('stock_filter')
                                ->label('Stock Filter')
                                ->options([
                                    'all' => 'All Products',
                                    'in_stock' => 'In Stock Only',
                                    'out_of_stock' => 'Out of Stock Only',
                                ])
                                ->default('all')
                                ->required(),
                        ])
                        ->columns(2),

                    Section::make('Price Display Options')
                        ->schema([
                            Checkbox::make('include_selling_price')
                                ->label('Include Selling Price')
                                ->default(true),

                            Checkbox::make('include_cost_price')
                                ->label('Include Cost Price (with Other Costs column)')
                                ->default(false)
                                ->helperText('For internal use only. Adds an empty "Other Costs" column for manual calculations.'),
                        ])
                        ->columns(2),
                ])
                    ->statePath('data'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->size(Size::Large)
                ->action(function () {
                    $data = $this->data;

                    // Build query
                    $query = Product::with('category');

                    // Apply category filter
                    if (! empty($data['category_id'])) {
                        $query->where('category_id', $data['category_id']);
                    }

                    // Apply stock filter
                    if (isset($data['stock_filter']) && $data['stock_filter'] === 'in_stock') {
                        $query->where('current_stock', '>', 0);
                    } elseif (isset($data['stock_filter']) && $data['stock_filter'] === 'out_of_stock') {
                        $query->where('current_stock', '<=', 0);
                    }

                    // Get products
                    $products = $query->orderBy('name')->get();

                    // Prepare PDF data
                    $pdfData = [
                        'products' => $products,
                        'include_selling_price' => $data['include_selling_price'] ?? true,
                        'include_cost_price' => $data['include_cost_price'] ?? false,
                        'category_filter' => ! empty($data['category_id'])
                            ? Category::find($data['category_id'])?->name
                            : null,
                        'stock_filter' => $data['stock_filter'] ?? 'all',
                        'generated_at' => now()->format('d.m.Y H:i'),
                    ];

                    // Generate PDF
                    $pdf = Pdf::loadView('pdf.product-catalog', $pdfData);
                    $pdf->setPaper('a4', 'landscape');

                    // Download
                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, 'product-catalog-'.now()->format('Y-m-d').'.pdf');
                }),
        ];
    }
}
