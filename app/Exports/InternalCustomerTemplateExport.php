<?php

namespace App\Exports;

use App\Config\ExcelColors;
use App\Models\BusinessType;
use App\Models\OneCharging;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InternalCustomerTemplateExport implements FromCollection, WithHeadings, WithEvents, WithTitle, WithColumnWidths
{
    public function collection()
    {
        return new Collection();
    }

    public function headings(): array
    {
        return [
            ["Internal Customer Importing Template"],
            [
                'ID Number',
                'First Name',
                'Middle Name',
                'Last Name',
                'Suffix',
                'Birth Date',
                'One Charging Sync ID',
                'One Charging',
                'Business Type ID',
                'Business Type',
                'Amount',
            ]
        ];
    }

    public function title(): string
    {
        return 'Internal Customer Template';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $statusText = " Internal Customer";
                $columnCount = 11;
                $mergeRange = 'A1:' . chr(65 + $columnCount - 1) . '1';

                // Merge cells and set title text
                $event->sheet->mergeCells($mergeRange);

                // Apply title styling with ExcelColors
                $event->sheet->getStyle($mergeRange)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['argb' => ExcelColors::HEADER_TEXT],
                        'name' => 'Century Gothic',
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => ExcelColors::HEADER_BG],
                    ],
                ]);

                // Create rich text with dynamic sizing
                $richText = new \PhpOffice\PhpSpreadsheet\RichText\RichText();
                $firstLetter = $richText->createTextRun(substr($statusText, 0, 1));
                $firstLetter->getFont()->setSize(20)->setBold(true)->setName('Century Gothic');

                $remainingText = $richText->createTextRun(substr($statusText, 1));
                $remainingText->getFont()->setSize(14)->setBold(true)->setName('Century Gothic');

                $event->sheet->getDelegate()->getCell('A1')->setValue($richText);

                // Apply heading style to row 2 with ExcelColors
                $event->sheet->getStyle('A2:K2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 11,
                        'color' => ['argb' => ExcelColors::HEADER_TEXT],
                        'name' => 'Century Gothic',
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => ExcelColors::PRIMARY_LIGHT],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => ExcelColors::BORDER_COLOR],
                        ],
                    ],
                ]);

                // Set row heights
                $event->sheet->getDelegate()->getRowDimension(1)->setRowHeight(30);
                $event->sheet->getDelegate()->getRowDimension(2)->setRowHeight(30);

                // Set default font for the sheet
                $event->sheet->getDelegate()->getParent()->getDefaultStyle()->applyFromArray([
                    'font' => [
                        'name' => 'Century Gothic',
                        'size' => 10,
                        'color' => ['argb' => ExcelColors::NEUTRAL_TEXT],
                    ],
                ]);

                $mainSheet = $event->sheet->getDelegate();
                $spreadsheet = $event->sheet->getDelegate()->getParent();

                // Apply alternating row colors and borders to data rows
                for ($row = 3; $row <= 100; $row++) {
                    $bgColor = ($row % 2 == 0) ? ExcelColors::ROW_EVEN_BG : ExcelColors::ROW_ODD_BG;
                    $event->sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $bgColor],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => ExcelColors::BORDER_COLOR],
                            ],
                        ],
                    ]);
                }

                // One Charging Lookup
                $oneChargingList = OneCharging::all(['sync_id', 'name']);

                $chargingLookupSheet = new Worksheet($spreadsheet, 'OneChargingLookup');
                $spreadsheet->addSheet($chargingLookupSheet);

                $chargingLookupSheet->setCellValue('A1', 'Label');
                $chargingLookupSheet->setCellValue('B1', 'Sync ID');
                $chargingLookupSheet->setCellValue('C1', 'Charging Name');

                // Style lookup sheet headers
                $chargingLookupSheet->getStyle('A1:C1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => ExcelColors::HEADER_TEXT],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => ExcelColors::SECONDARY_MAIN],
                    ],
                ]);

                $row = 2;
                foreach ($oneChargingList as $charging) {
                    $chargingLookupSheet->setCellValue("A{$row}", $charging->sync_id . ' - ' . ($charging->name ?? ''));
                    $chargingLookupSheet->setCellValue("B{$row}", $charging->sync_id ?? '');
                    $chargingLookupSheet->setCellValue("C{$row}", $charging->name ?? '');
                    $row++;
                }

                // Set data validation for One Charging Sync ID (column G)
                for ($row = 3; $row <= 100; $row++) {
                    $validation = $mainSheet->getCell("G{$row}")->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                    $validation->setAllowBlank(false);
                    $validation->setShowDropDown(true);
                    $validation->setFormula1("=OneChargingLookup!\$A\$2:\$A\$1000");

                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Invalid Entry');
                    $validation->setError('Please select a value from the dropdown list only.');
                }

                // Auto-populate One Charging name (column H)
                for ($row = 3; $row <= 1000; $row++) {
                    $mainSheet->setCellValue("H{$row}", "=IFERROR(VLOOKUP(G{$row}, OneChargingLookup!A:C, 3, FALSE), \"\")");
                }

                $chargingLookupSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

                // Business Type Lookup
                $businessTypeList = BusinessType::all(['id', 'name']);

                $businessTypeLookupSheet = new Worksheet($spreadsheet, 'BusinessTypeLookup');
                $spreadsheet->addSheet($businessTypeLookupSheet);

                $businessTypeLookupSheet->setCellValue('A1', 'Label');
                $businessTypeLookupSheet->setCellValue('B1', 'Business Type ID');
                $businessTypeLookupSheet->setCellValue('C1', 'Business Type Name');

                // Style lookup sheet headers
                $businessTypeLookupSheet->getStyle('A1:C1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => ExcelColors::HEADER_TEXT],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => ExcelColors::SECONDARY_MAIN],
                    ],
                ]);

                $row = 2;
                foreach ($businessTypeList as $businessType) {
                    $businessTypeLookupSheet->setCellValue("A{$row}", $businessType->id . ' - ' . ($businessType->name ?? ''));
                    $businessTypeLookupSheet->setCellValue("B{$row}", $businessType->id ?? '');
                    $businessTypeLookupSheet->setCellValue("C{$row}", $businessType->name ?? '');
                    $row++;
                }

                // Set data validation for Business Type ID (column I)
                for ($row = 3; $row <= 100; $row++) {
                    $validation = $mainSheet->getCell("I{$row}")->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                    $validation->setAllowBlank(false);
                    $validation->setShowDropDown(true);
                    $validation->setFormula1("=BusinessTypeLookup!\$A\$2:\$A\$100");

                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Invalid Entry');
                    $validation->setError('Please select a value from the dropdown list only.');
                }

                // Auto-populate Business Type name (column J)
                for ($row = 3; $row <= 100; $row++) {
                    $mainSheet->setCellValue("J{$row}", "=IFERROR(VLOOKUP(I{$row}, BusinessTypeLookup!A:C, 3, FALSE), \"\")");
                }

                $businessTypeLookupSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

                // Set date format for Birth Date column (column F)
                for ($row = 3; $row <= 100; $row++) {
                    $mainSheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                }

                // Set number format for Amount column (column K)
                for ($row = 3; $row <= 100; $row++) {
                    $mainSheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0');
                }

                // Highlight required columns with a subtle accent
                $requiredColumns = ['A', 'B', 'D', 'F', 'G', 'I', 'K']; // Required fields
                foreach ($requiredColumns as $col) {
                    $event->sheet->getStyle("{$col}2")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['argb' => ExcelColors::HEADER_TEXT],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => ExcelColors::PRIMARY_MAIN],
                        ],
                    ]);
                }
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // ID Number
            'B' => 20, // First Name
            'C' => 20, // Middle Name
            'D' => 20, // Last Name
            'E' => 10, // Suffix
            'F' => 15, // Birth Date
            'G' => 35, // One Charging Sync ID
            'H' => 50, // One Charging
            'I' => 20, // Business Type ID
            'J' => 30, // Business Type
            'K' => 15, // Amount
        ];
    }
}
