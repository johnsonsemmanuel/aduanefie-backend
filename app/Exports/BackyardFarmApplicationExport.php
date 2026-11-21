<?php

namespace App\Exports;

use App\Models\BackyardFarmApplication;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BackyardFarmApplicationExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(protected $query)
    {
    }

    public function headings(): array
    {
        return [
            'ID',
            'Farm Name',
            'Status',
            'Farm Size (acres)',
            'Primary Crops',
            'Address',
            'Email',
            'Phone',
            'Latitude',
            'Longitude',
            'Zone ID',
            'Terms Accepted',
            'Verified By',
            'Verified At',
            'Vendor ID',
            'Store ID',
            'Converted At',
            'Created At',
        ];
    }

    public function map($application): array
    {
        return [
            $application->id,
            $application->farm_name,
            ucfirst($application->status),
            $application->farm_size_acres ?? '-',
            $application->primary_crops ?? '-',
            $application->address ?? '-',
            $application->guest_email ?? '-',
            $application->guest_phone ?? '-',
            $application->latitude ?? '-',
            $application->longitude ?? '-',
            $application->zone_id ?? '-',
            $application->terms_accepted ? 'Yes' : 'No',
            $application->verifier?->f_name . ' ' . $application->verifier?->l_name ?? '-',
            $application->verified_at?->format('Y-m-d H:i:s') ?? '-',
            $application->vendor_id ?? '-',
            $application->store_id ?? '-',
            $application->converted_at?->format('Y-m-d H:i:s') ?? '-',
            $application->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function collection()
    {
        return $this->query->get();
    }
}
