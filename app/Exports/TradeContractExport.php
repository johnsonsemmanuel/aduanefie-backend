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

class TradeContractExport implements FromCollection, ShouldAutoSize, WithHeadings, WithProperties, WithColumnWidths, WithStyles
{
    use Exportable;

    protected $tradeContracts;

    public function __construct($tradeContracts)
    {
        $this->tradeContracts = $tradeContracts;
    }

    public function collection()
    {
        return $this->tradeContracts->map(function ($contract) {
            return [
                $contract->id,
                $contract->tradeRequest?->product?->name ?? 'N/A',
                $contract->buyer_id,
                $contract->seller_id,
                $contract->contract_amount,
                $contract->currency_code,
                $contract->escrow_amount,
                $contract->payment_status,
                $contract->delivery_status,
                $contract->delivery_confirmed_at ? $contract->delivery_confirmed_at->format('Y-m-d H:i:s') : '-',
                $contract->docs_verified_at ? $contract->docs_verified_at->format('Y-m-d H:i:s') : '-',
                $contract->funds_released_at ? $contract->funds_released_at->format('Y-m-d H:i:s') : '-',
                $contract->created_at->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Contract ID',
            'Product',
            'Buyer ID',
            'Seller ID',
            'Contract Amount',
            'Currency',
            'Escrow Amount',
            'Payment Status',
            'Delivery Status',
            'Delivery Confirmed At',
            'Docs Verified At',
            'Funds Released At',
            'Created At',
        ];
    }

    public function properties(): array
    {
        return [
            'creator' => 'Aduanefie Marketplace',
            'title' => 'Trade Contracts Report',
            'description' => 'Intra-African agricultural trade contracts export',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 30,
            'C' => 12,
            'D' => 12,
            'E' => 18,
            'F' => 12,
            'G' => 18,
            'H' => 20,
            'I' => 20,
            'J' => 20,
            'K' => 20,
            'L' => 20,
            'M' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getFill()->applyFromArray([
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'color' => ['rgb' => '006161'],
        ]);
        $sheet->getStyle('A1:M1')->getFont()->getColor()->setARGB('FFFFFF');

        $lastRow = $this->tradeContracts->count() + 1;
        $sheet->getStyle("A1:M{$lastRow}")->applyFromArray([
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
