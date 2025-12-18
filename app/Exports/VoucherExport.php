<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Config\ExcelColors;
use Carbon\Carbon;

class VoucherExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $vouchers;
    protected $targetDate;

    public function __construct($vouchers, $targetDate = null)
    {
        $this->vouchers = $vouchers;
        $this->targetDate = $targetDate ?? now();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->vouchers;
    }

    /**
     * Define the headings
     */
    public function headings(): array
    {
        return [
            'ID',
            'Reference Number',
            'Amount',
            'Business Type',
            'Customer Type',
            'ID Number',
            'Customer Name',
            'One Charging Code',
            'One Charging Name',
            'Redeemed By',
            'Redeemed By Role',
            'Claimed Date',
            'Status',
            'Created At',
        ];
    }

    /**
     * Map the data for each row
     */
    public function map($voucher): array
    {
        $customerType = '';
        $customerName = '';
        $idNumber = '';
        $oneChargingCode = '';
        $oneChargingName = '';

        // Check if internal customer
        if ($voucher->voucherable && isset($voucher->voucherable->id_no)) {
            $customerType = 'Internal';
            $customerName = trim(implode(' ', array_filter([
                $voucher->voucherable->first_name,
                $voucher->voucherable->middle_name,
                $voucher->voucherable->last_name,
                $voucher->voucherable->suffix,
            ])));
            $idNumber = $voucher->voucherable->id_no;
            $oneChargingCode = $voucher->voucherable->one_charging->code ?? '';
            $oneChargingName = $voucher->voucherable->one_charging->name ?? '';
        }
        // Check if external customer
        elseif ($voucher->voucherable && isset($voucher->voucherable->name)) {
            $customerType = 'External';
            $customerName = $voucher->voucherable->name;
        }

        return [
            $voucher->id,
            $voucher->reference_number ?? '',
            number_format($voucher->amount, 2),
            $voucher->business_type->name ?? '',
            $customerType,
            $idNumber,
            $customerName,
            $oneChargingCode,
            $oneChargingName,
            $voucher->redeemed_by_user->name ?? '',
            $voucher->redeemed_by_user->role_type ?? '',
            $voucher->claimed_date ? Carbon::parse($voucher->claimed_date)->format('Y-m-d H:i:s') : '',
            $voucher->status,
            $voucher->created_at ? Carbon::parse($voucher->created_at)->format('Y-m-d H:i:s') : '',
        ];
    }

    /**
     * Apply styles to the sheet
     */
    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        // Header styling
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 13, // Header font size
                'name' => 'Century Gothic', // Font
                'color' => ['rgb' => ExcelColors::HEADER_TEXT],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => ExcelColors::HEADER_BG],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => ExcelColors::PRIMARY_DARK],
                ],
            ],
        ]);

        // Apply row colors and font
        for ($row = 2; $row <= $highestRow; $row++) {
            $claimedDate = $sheet->getCell('L' . $row)->getValue();
            $status = $sheet->getCell('N' . $row)->getValue();

            // Default zebra stripe
            $bgColor = $row % 2 === 0 ? ExcelColors::ROW_EVEN_BG : ExcelColors::ROW_ODD_BG;
            $textColor = ExcelColors::NEUTRAL_TEXT;

            // Override colors based on status/date
            if ($claimedDate && $this->isDatePassed($claimedDate)) {
                $bgColor = ExcelColors::PASSED_DATE_BG;
                $textColor = ExcelColors::PASSED_DATE_TEXT;
            } elseif ($status === 'Available') {
                $bgColor = ExcelColors::STATUS_AVAILABLE_BG;
                $textColor = ExcelColors::STATUS_AVAILABLE_TEXT;
            } elseif ($status === 'Redeemed') {
                $bgColor = ExcelColors::STATUS_REDEEMED_BG;
                $textColor = ExcelColors::STATUS_REDEEMED_TEXT;
            }

            // Apply styling
            $sheet->getStyle('A' . $row . ':N' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $bgColor],
                ],
                'font' => [
                    'size' => 11, // Row font size
                    'name' => 'Century Gothic',
                    'color' => ['rgb' => $textColor],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => ExcelColors::BORDER_COLOR],
                    ],
                ],
            ]);

            // Bold the status column
            $sheet->getStyle('N' . $row)->getFont()->setBold(true);
        }

        // Auto-height rows
        foreach (range(1, $highestRow) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(-1);
        }

        return [];
    }

    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        return [
            'A' => 10,   // ID
            'B' => 25,  // Reference Number
            'C' => 12,  // Amount
            'D' => 20,  // Business Type
            'E' => 20,  // Customer Type
            'F' => 20,  // ID NUMBER
            'G' => 45,  // Customer Name
            'H' => 30,  // One Charging Code
            'I' => 50,  // One Charging Name
            'J' => 25,  // Redeemed By
            'K' => 20,  // Redeemed By Role
            'L' => 20,  // Claimed Date
            'M' => 15,  // Status
            'N' => 20,  // Created At
        ];
    }

    /**
     * Check if the date has passed the target date
     */
    protected function isDatePassed($date): bool
    {
        try {
            $claimedDate = Carbon::parse($date);
            $targetDate = Carbon::parse($this->targetDate);
            return $claimedDate->lt($targetDate);
        } catch (\Exception $e) {
            return false;
        }
    }
}
