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
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class InternalCustomerTemplateExport
implements FromCollection, WithHeadings, WithEvents, WithTitle, WithColumnWidths
{
    public function collection()
    {
        return new Collection();
    }

    public function headings(): array
    {
        return [
            ['Internal Customer Importing Template'],
            [
                'Voucher ID', // Added for updates
                'ID Number',
                'First Name',
                'Middle Name',
                'Last Name',
                'Suffix',
                'Birth Date',

                // One Charging
                'One Charging',
                'One Charging ID',
                'One Charging Name',

                // Business Type
                'Business Type',
                'Business Type ID',

                'Amount',
            ],
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

                $mainSheet   = $event->sheet->getDelegate();
                $spreadsheet = $mainSheet->getParent();

                /* =======================
                 * TITLE
                 * ======================= */
                $columnCount = 13; // A–M (added one more column)
                $mergeRange  = 'A1:' . chr(64 + $columnCount) . '1';

                $mainSheet->mergeCells($mergeRange);

                $mainSheet->getStyle($mergeRange)->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 16,
                        'name'  => 'Century Gothic',
                        'color' => ['argb' => ExcelColors::HEADER_TEXT],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => ExcelColors::HEADER_BG],
                    ],
                ]);

                $richText = new RichText();
                $richText->createTextRun('Internal ')
                    ->getFont()->setSize(20)->setBold(true)->setName('Century Gothic');
                $richText->createTextRun('Customer')
                    ->getFont()->setSize(14)->setBold(true)->setName('Century Gothic');

                $mainSheet->setCellValue('A1', $richText);

                /* =======================
                 * HEADER STYLE
                 * ======================= */
                $mainSheet->getStyle('A2:M2')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 11,
                        'name'  => 'Century Gothic',
                        'color' => ['argb' => ExcelColors::HEADER_TEXT],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => ExcelColors::PRIMARY_LIGHT],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => ExcelColors::BORDER_COLOR],
                        ],
                    ],
                ]);

                $mainSheet->getRowDimension(1)->setRowHeight(30);
                $mainSheet->getRowDimension(2)->setRowHeight(30);

                $spreadsheet->getDefaultStyle()->getFont()
                    ->setName('Century Gothic')
                    ->setSize(10)
                    ->getColor()->setARGB(ExcelColors::NEUTRAL_TEXT);

                /* =======================
                 * ROW STYLING
                 * ======================= */
                for ($row = 3; $row <= 100; $row++) {
                    $bg = $row % 2 === 0
                        ? ExcelColors::ROW_EVEN_BG
                        : ExcelColors::ROW_ODD_BG;

                    $mainSheet->getStyle("A{$row}:M{$row}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $bg],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['argb' => ExcelColors::BORDER_COLOR],
                            ],
                        ],
                    ]);
                }

                /* =======================
                 * ONE CHARGING LOOKUP
                 * ======================= */
                $chargingSheet = new Worksheet($spreadsheet, 'OneChargingLookup');
                $spreadsheet->addSheet($chargingSheet);

                $chargingSheet->fromArray(
                    ['Label', 'Sync ID', 'Charging Name'],
                    null,
                    'A1'
                );

                $row = 2;
                foreach (OneCharging::all(['sync_id', 'name']) as $charging) {
                    $chargingSheet->setCellValue("A{$row}", "{$charging->sync_id} - {$charging->name}");
                    $chargingSheet->setCellValue("B{$row}", $charging->sync_id);
                    $chargingSheet->setCellValue("C{$row}", $charging->name);
                    $row++;
                }

                // Dropdown (H) - shifted from G
                for ($r = 3; $r <= 2000; $r++) {
                    $dv = $mainSheet->getCell("H{$r}")->getDataValidation();
                    $dv->setType(DataValidation::TYPE_LIST);
                    $dv->setShowDropDown(true);
                    $dv->setFormula1("=OneChargingLookup!\$A\$2:\$A\$1000");
                }

                // Auto-fill ID (I) and Name (J) - shifted from H and I
                for ($r = 3; $r <= 2000; $r++) {
                    $mainSheet->setCellValue(
                        "I{$r}",
                        "=IFERROR(VLOOKUP(H{$r}, OneChargingLookup!A:B, 2, FALSE), \"\")"
                    );
                    $mainSheet->setCellValue(
                        "J{$r}",
                        "=IFERROR(VLOOKUP(H{$r}, OneChargingLookup!A:C, 3, FALSE), \"\")"
                    );
                }

                $chargingSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

                /* =======================
                 * BUSINESS TYPE LOOKUP
                 * ======================= */
                $btSheet = new Worksheet($spreadsheet, 'BusinessTypeLookup');
                $spreadsheet->addSheet($btSheet);

                $btSheet->fromArray(
                    ['Label', 'Business Type ID', 'Business Type Name'],
                    null,
                    'A1'
                );

                $row = 2;
                foreach (BusinessType::all(['id', 'name']) as $bt) {
                    $btSheet->setCellValue("A{$row}", "{$bt->id} - {$bt->name}");
                    $btSheet->setCellValue("B{$row}", $bt->id);
                    $btSheet->setCellValue("C{$row}", $bt->name);
                    $row++;
                }

                // Dropdown (K) - shifted from J
                for ($r = 3; $r <= 100; $r++) {
                    $dv = $mainSheet->getCell("K{$r}")->getDataValidation();
                    $dv->setType(DataValidation::TYPE_LIST);
                    $dv->setShowDropDown(true);
                    $dv->setFormula1("=BusinessTypeLookup!\$A\$2:\$A\$100");
                }

                // Auto-fill Business Type ID (L) - shifted from K
                for ($r = 3; $r <= 100; $r++) {
                    $mainSheet->setCellValue(
                        "L{$r}",
                        "=IFERROR(VLOOKUP(K{$r}, BusinessTypeLookup!A:B, 2, FALSE), \"\")"
                    );
                }

                $btSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

                /* =======================
                 * FORMATS
                 * ======================= */
                for ($r = 3; $r <= 100; $r++) {
                    // Birth Date format (G) - shifted from F
                    $mainSheet->getStyle("G{$r}")
                        ->getNumberFormat()->setFormatCode('yyyy-mm-dd');

                    // Amount format (M) - shifted from L
                    $mainSheet->getStyle("M{$r}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }

                /* =======================
                 * REQUIRED COLUMNS
                 * ======================= */
                // Updated: B, C, E, G, H, K, M (shifted due to new Voucher ID column)
                // Note: Voucher ID (A) is optional for new records, required for updates
                foreach (['B', 'C', 'E', 'G', 'H', 'K', 'M'] as $col) {
                    $mainSheet->getStyle("{$col}2")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
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
            'A' => 15, // Voucher ID (new)
            'B' => 20, // ID Number
            'C' => 20, // First Name
            'D' => 20, // Middle Name
            'E' => 20, // Last Name
            'F' => 10, // Suffix
            'G' => 15, // Birth Date

            'H' => 35, // One Charging (dropdown)
            'I' => 20, // One Charging ID
            'J' => 40, // One Charging Name

            'K' => 35, // Business Type (dropdown)
            'L' => 30, // Business Type ID

            'M' => 15, // Amount
        ];
    }
}
