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
                $columnCount = 12; // A–L
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
                $mainSheet->getStyle('A2:L2')->applyFromArray([
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

                    $mainSheet->getStyle("A{$row}:L{$row}")->applyFromArray([
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

                // Dropdown (G)
                for ($r = 3; $r <= 2000; $r++) {
                    $dv = $mainSheet->getCell("G{$r}")->getDataValidation();
                    $dv->setType(DataValidation::TYPE_LIST);
                    $dv->setShowDropDown(true);
                    $dv->setFormula1("=OneChargingLookup!\$A\$2:\$A\$1000");
                }

                // Auto-fill ID (H) and Name (I)
                for ($r = 3; $r <= 2000; $r++) {
                    $mainSheet->setCellValue(
                        "H{$r}",
                        "=IFERROR(VLOOKUP(G{$r}, OneChargingLookup!A:B, 2, FALSE), \"\")"
                    );
                    $mainSheet->setCellValue(
                        "I{$r}",
                        "=IFERROR(VLOOKUP(G{$r}, OneChargingLookup!A:C, 3, FALSE), \"\")"
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

                // Dropdown (J)
                for ($r = 3; $r <= 100; $r++) {
                    $dv = $mainSheet->getCell("J{$r}")->getDataValidation();
                    $dv->setType(DataValidation::TYPE_LIST);
                    $dv->setShowDropDown(true);
                    $dv->setFormula1("=BusinessTypeLookup!\$A\$2:\$A\$100");
                }

                // Auto-fill Business Type ID (K)
                for ($r = 3; $r <= 100; $r++) {
                    $mainSheet->setCellValue(
                        "K{$r}",
                        "=IFERROR(VLOOKUP(J{$r}, BusinessTypeLookup!A:B, 2, FALSE), \"\")"
                    );
                }

                $btSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

                /* =======================
                 * FORMATS
                 * ======================= */
                for ($r = 3; $r <= 100; $r++) {
                    $mainSheet->getStyle("F{$r}")
                        ->getNumberFormat()->setFormatCode('yyyy-mm-dd');

                    $mainSheet->getStyle("L{$r}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }

                /* =======================
                 * REQUIRED COLUMNS
                 * ======================= */
                foreach (['A', 'B', 'D', 'F', 'G', 'J', 'L'] as $col) {
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
            'A' => 20,
            'B' => 20,
            'C' => 20,
            'D' => 20,
            'E' => 10,
            'F' => 15,

            'G' => 35, // One Charging (dropdown)
            'H' => 20, // One Charging ID
            'I' => 40, // One Charging Name

            'J' => 30, // Business Type
            'K' => 20, // Business Type ID

            'L' => 15, // Amount
        ];
    }
}
