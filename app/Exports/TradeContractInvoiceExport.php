<?php

namespace App\Exports;

use App\Models\TradeContract;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\Exportable;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class TradeContractInvoiceExport implements FromCollection, ShouldAutoSize, WithHeadings, WithProperties, WithColumnWidths, WithStyles
{
    use Exportable;

    protected $tradeContract;

    public function __construct($tradeContract)
    {
        $this->tradeContract = $tradeContract;
    }

    public function collection()
    {
        return collect([
            [
                'Invoice ID' => 'INV-' . $this->tradeContract->id,
                'Contract ID' => $this->tradeContract->id,
                'Buyer' => $this->tradeContract->buyer_id,
                'Seller' => $this->tradeContract->seller_id,
                'Product' => $this->tradeContract->tradeRequest?->product?->name ?? 'N/A',
                'Quantity' => $this->tradeContract->tradeRequest?->quantity ?? 0,
                'Unit' => $this->tradeContract->tradeRequest?->unit ?? 'kg',
                'Destination Country' => $this->tradeContract->tradeRequest?->destination_country ?? 'N/A',
                'Origin Country' => $this->tradeContract->tradeRequest?->origin_country ?? 'N/A',
                'Incoterm' => $this->tradeContract->tradeRequest?->incoterm ?? 'FOB',
                'Contract Amount' => $this->tradeContract->contract_amount,
                'Currency' => $this->tradeContract->currency_code,
                'Escrow Amount' => $this->tradeContract->escrow_amount,
                'Payment Status' => $this->tradeContract->payment_status,
                'Delivery Status' => $this->tradeContract->delivery_status,
                'Created At' => $this->tradeContract->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function headings(): array
    {
        return [
            'Invoice ID',
            'Contract ID',
            'Buyer',
            'Seller',
            'Product',
            'Quantity',
            'Unit',
            'Destination Country',
            'Origin Country',
            'Incoterm',
            'Contract Amount',
            'Currency',
            'Escrow Amount',
            'Payment Status',
            'Delivery Status',
            'Created At',
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => 'Aduanefie Marketplace',
            'title' => 'Trade Invoice',
            'description' => 'Intra-African agricultural trade invoice',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 15,
            'C' => 12,
            'D' => 12,
            'E' => 30,
            'F' => 12,
            'G' => 10,
            'H' => 20,
            'I' => 20,
            'J' => 12,
            'K' => 18,
            'L' => 12,
            'M' => 18,
            'N' => 20,
            'O' => 20,
            'P' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getFill()->applyFromArray([
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'color' => ['rgb' => '006161'],
        ]);
        $sheet->getStyle('A1:P1')->getFont()->getColor()->setARGB('FFFFFF');

        $sheet->getStyle('A1:P2')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ]);

        return [];
    }
}
