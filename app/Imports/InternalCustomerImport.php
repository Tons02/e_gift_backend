<?php

namespace App\Imports;

use App\Models\InternalCustomer;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Illuminate\Support\Facades\Log;

class InternalCustomerImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithStartRow, WithCalculatedFormulas
{
    protected $errors = [];
    protected $successCount = 0;
    protected $errorCount = 0;

    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        // Debug: Log the first row to see what we're getting
        if ($rows->isNotEmpty()) {
            Log::info('First row data:', $rows->first()->toArray());
        }

        // Filter out completely empty rows
        $rows = $rows->filter(function ($row) {
            return !empty($row['id_number']) && trim($row['id_number']) !== '';
        });

        Log::info('Total valid rows after filtering:', ['count' => $rows->count()]);

        // Group rows by ID Number to handle multiple vouchers per employee
        $groupedData = $rows->groupBy('id_number');

        DB::beginTransaction();

        try {
            foreach ($groupedData as $idNumber => $employeeRows) {
                try {
                    $this->processEmployee($idNumber, $employeeRows);
                    $this->successCount++;
                } catch (\Exception $e) {
                    $this->errorCount++;
                    $this->errors[] = [
                        'id_number' => $idNumber,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Import error for ' . $idNumber, ['error' => $e->getMessage()]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process individual employee with their vouchers
     */
    protected function processEmployee($idNumber, Collection $rows)
    {
        // Validate that all rows for this employee have the same employee details
        $this->validateConsistentEmployeeData($idNumber, $rows);

        // Get the first row for employee details (all rows have same employee info)
        $firstRow = $rows->first();

        // Check if this is an update operation (has voucher_id) or create operation
        $isUpdate = $rows->contains(function ($row) {
            $voucherId = $this->cleanValue($row['voucher_id'] ?? null);
            return !empty($voucherId);
        });

        // Validate employee data
        $employeeData = $this->validateEmployeeData($firstRow);

        // Look up the actual one_charging ID from sync_id
        $oneCharging = \App\Models\OneCharging::where('sync_id', $employeeData['one_charging_sync_id'])->first();

        if (!$oneCharging) {
            throw new \Exception("One Charging with sync_id {$employeeData['one_charging_sync_id']} not found in database.");
        }

        // Check if employee exists
        $existingEmployee = InternalCustomer::where('id_no', $employeeData['id_no'])->first();

        if ($isUpdate) {
            // UPDATE MODE: Employee must exist
            if (!$existingEmployee) {
                throw new \Exception("Cannot update. Employee with ID Number {$idNumber} does not exist in the system.");
            }

            // Validate vouchers data for update
            $vouchersData = $this->validateVouchersDataForUpdate($rows, $existingEmployee);

            // Update employee information
            $existingEmployee->update([
                'first_name' => $employeeData['first_name'],
                'middle_name' => $employeeData['middle_name'],
                'last_name' => $employeeData['last_name'],
                'suffix' => $employeeData['suffix'],
                'birth_date' => $employeeData['birth_date'],
                'one_charging_sync_id' => $oneCharging->id,
            ]);

            // Update vouchers
            foreach ($vouchersData as $voucherData) {
                $voucher = Voucher::find($voucherData['voucher_id']);

                $voucher->update([
                    'business_type_id' => $voucherData['business_type_id'],
                    'amount' => $voucherData['amount'],
                ]);
            }

            Log::info("Updated employee and vouchers for {$idNumber}");
        } else {
            // CREATE MODE: Check for claimed vouchers if employee exists
            if ($existingEmployee) {
                $claimedVouchers = Voucher::where('customer_id', $existingEmployee->id)
                    ->where('customer_type', InternalCustomer::class)
                    ->where('status', '!=', 'Available')
                    ->get();

                if ($claimedVouchers->count() > 0) {
                    $statusList = $claimedVouchers->pluck('status')->unique()->implode(', ');
                    throw new \Exception("Cannot update employee {$idNumber}. This employee has {$claimedVouchers->count()} voucher(s) with status: {$statusList}. Only vouchers with 'Available' status can be updated.");
                }
            }

            // Validate vouchers data for creation
            $vouchersData = $this->validateVouchersData($rows);

            // Create or update employee
            $employee = InternalCustomer::updateOrCreate(
                ['id_no' => $employeeData['id_no']],
                [
                    'first_name' => $employeeData['first_name'],
                    'middle_name' => $employeeData['middle_name'],
                    'last_name' => $employeeData['last_name'],
                    'suffix' => $employeeData['suffix'],
                    'birth_date' => $employeeData['birth_date'],
                    'one_charging_sync_id' => $oneCharging->id,
                ]
            );

            // Delete existing vouchers if updating (only Available ones)
            if ($employee->wasRecentlyCreated === false) {
                Voucher::where('customer_id', $employee->id)
                    ->where('customer_type', InternalCustomer::class)
                    ->where('status', 'Available')
                    ->forceDelete();
            }

            // Create vouchers with polymorphic relationship
            foreach ($vouchersData as $voucherData) {
                Voucher::create([
                    'business_type_id' => $voucherData['business_type_id'],
                    'reference_number' => Carbon::now()->format('Ymd') . Str::upper(Str::random(8)),
                    'amount' => $voucherData['amount'],
                    'customer_id' => $employee->id,
                    'customer_type' => InternalCustomer::class,
                    'status' => 'Available',
                ]);
            }

            Log::info("Created/replaced employee and vouchers for {$idNumber}");
        }
    }

    /**
     * Validate vouchers data for UPDATE operations
     */
    protected function validateVouchersDataForUpdate(Collection $rows, InternalCustomer $employee)
    {
        $vouchers = [];
        $voucherIds = [];
        $businessTypeIds = [];

        foreach ($rows as $index => $row) {
            $voucherId = $this->cleanValue($row['voucher_id'] ?? null);

            if (empty($voucherId)) {
                throw new \Exception("Voucher ID is required for update operations at row " . ($index + 1));
            }

            // Validate voucher exists
            $voucher = Voucher::find($voucherId);
            if (!$voucher) {
                throw new \Exception("Voucher with ID {$voucherId} does not exist at row " . ($index + 1));
            }

            // Validate voucher belongs to this employee
            if ($voucher->customer_id != $employee->id || $voucher->customer_type != InternalCustomer::class) {
                throw new \Exception("Voucher ID {$voucherId} does not belong to employee {$employee->id_no} at row " . ($index + 1));
            }

            // Validate voucher status is Available
            if ($voucher->status != 'Available') {
                throw new \Exception("Voucher ID {$voucherId} has status '{$voucher->status}' and cannot be updated. Only 'Available' vouchers can be updated at row " . ($index + 1));
            }

            // Check for duplicate voucher_id in the import
            if (in_array($voucherId, $voucherIds)) {
                throw new \Exception("Duplicate Voucher ID {$voucherId} found in import file");
            }

            $businessTypeId = $this->cleanValue($row['business_type_id'] ?? null);

            // If business_type_id is empty or is a formula, try to extract from business_type column
            if (empty($businessTypeId) || strpos($businessTypeId, '=') === 0) {
                $businessTypeText = $this->cleanValue($row['business_type'] ?? null);
                if ($businessTypeText && preg_match('/^(\d+)\s*-/', $businessTypeText, $matches)) {
                    $businessTypeId = (int) $matches[1];
                }
            } else {
                $businessTypeId = (int) $businessTypeId;
            }

            $amount = $this->cleanAmount($row['amount'] ?? null);

            // Validate voucher data
            $validator = Validator::make([
                'voucher_id' => $voucherId,
                'business_type_id' => $businessTypeId,
                'amount' => $amount,
            ], [
                'voucher_id' => 'required|integer',
                'business_type_id' => 'required|integer|exists:business_types,id',
                'amount' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                throw new \Exception("Voucher validation failed at row " . ($index + 1) . ": " . $validator->errors()->first());
            }

            // Check for duplicate business_type_id
            if (in_array($businessTypeId, $businessTypeIds)) {
                throw new \Exception("Duplicate business_type_id {$businessTypeId} found for employee at row " . ($index + 1));
            }

            $voucherIds[] = $voucherId;
            $businessTypeIds[] = $businessTypeId;
            $vouchers[] = $validator->validated();
        }

        if (empty($vouchers)) {
            throw new \Exception("At least one voucher is required");
        }

        return $vouchers;
    }

    /**
     * Validate that all rows for the same employee have consistent data
     */
    protected function validateConsistentEmployeeData($idNumber, Collection $rows)
    {
        if ($rows->count() <= 1) {
            return; // Only one row, no need to check consistency
        }

        $firstRow = $rows->first();
        $inconsistencies = [];

        // Fields to check for consistency
        $fieldsToCheck = [
            'first_name' => 'First Name',
            'middle_name' => 'Middle Name',
            'last_name' => 'Last Name',
            'suffix' => 'Suffix',
            'birth_date' => 'Birth Date',
            'one_charging_id' => 'One Charging ID',
        ];

        foreach ($rows as $index => $row) {
            foreach ($fieldsToCheck as $field => $label) {
                $firstValue = $this->cleanValue($firstRow[$field] ?? null);
                $currentValue = $this->cleanValue($row[$field] ?? null);

                // Normalize values for comparison
                if ($field === 'one_charging_id') {
                    // Handle VLOOKUP formulas
                    if (empty($firstValue) || strpos($firstValue, '=') === 0) {
                        $oneChargingText = $this->cleanValue($firstRow['one_charging'] ?? null);
                        if ($oneChargingText && preg_match('/^(\d+)\s*-/', $oneChargingText, $matches)) {
                            $firstValue = $matches[1];
                        }
                    }
                    if (empty($currentValue) || strpos($currentValue, '=') === 0) {
                        $oneChargingText = $this->cleanValue($row['one_charging'] ?? null);
                        if ($oneChargingText && preg_match('/^(\d+)\s*-/', $oneChargingText, $matches)) {
                            $currentValue = $matches[1];
                        }
                    }
                }

                if ($firstValue != $currentValue) {
                    $inconsistencies[] = sprintf(
                        "%s mismatch: Row 1 has '%s' but Row %d has '%s'",
                        $label,
                        $firstValue ?? 'empty',
                        $index + 1,
                        $currentValue ?? 'empty'
                    );
                }
            }
        }

        if (!empty($inconsistencies)) {
            throw new \Exception(
                "Employee {$idNumber} has inconsistent data across multiple rows:\n" .
                    implode("\n", $inconsistencies)
            );
        }
    }

    /**
     * Validate employee data
     */
    protected function validateEmployeeData($row)
    {
        $birthDate = $this->cleanValue($row['birth_date'] ?? null);

        // Log the raw birth_date value for debugging
        Log::info('Raw birth_date value:', ['value' => $birthDate, 'type' => gettype($birthDate)]);

        // Convert Excel date serial number to proper date format if needed
        if ($birthDate && is_numeric($birthDate)) {
            try {
                $birthDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($birthDate)->format('Y-m-d');
                Log::info('Converted birth_date:', ['value' => $birthDate]);
            } catch (\Exception $e) {
                Log::error('Failed to convert birth_date:', ['error' => $e->getMessage()]);
            }
        } elseif ($birthDate && strpos($birthDate, '/') !== false) {
            // Handle MM/DD/YYYY or DD/MM/YYYY format
            try {
                $birthDate = \Carbon\Carbon::parse($birthDate)->format('Y-m-d');
                Log::info('Parsed birth_date from slash format:', ['value' => $birthDate]);
            } catch (\Exception $e) {
                Log::error('Failed to parse birth_date:', ['error' => $e->getMessage()]);
            }
        }

        // Clean and convert one_charging_id to integer
        $oneChargingId = $this->cleanValue($row['one_charging_id'] ?? null);

        // If one_charging_id is empty or is a formula, try to extract from one_charging column
        if (empty($oneChargingId) || strpos($oneChargingId, '=') === 0) {
            $oneChargingText = $this->cleanValue($row['one_charging'] ?? null);
            if ($oneChargingText && preg_match('/^(\d+)\s*-/', $oneChargingText, $matches)) {
                $oneChargingId = (int) $matches[1];
            }
        } else {
            $oneChargingId = (int) $oneChargingId;
        }

        $data = [
            'id_no' => $this->cleanValue($row['id_number'] ?? null),
            'first_name' => $this->cleanValue($row['first_name'] ?? null),
            'middle_name' => $this->cleanValue($row['middle_name'] ?? null),
            'last_name' => $this->cleanValue($row['last_name'] ?? null),
            'suffix' => $this->cleanValue($row['suffix'] ?? null),
            'birth_date' => $birthDate,
            'one_charging_sync_id' => $oneChargingId,
        ];

        Log::info('Validating employee data:', $data);

        $validator = Validator::make($data, [
            'id_no' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:50',
            'birth_date' => 'required|date|before:today',
            'one_charging_sync_id' => 'required|integer|exists:one_chargings,sync_id',
        ], [
            'birth_date.required' => 'The birth date field is required.',
            'birth_date.date' => 'The birth date must be a valid date (YYYY-MM-DD format). Received: ' . $birthDate,
            'birth_date.before' => 'The birth date must be before today.',
            'one_charging_sync_id.required' => 'The one charging ID field is required.',
            'one_charging_sync_id.integer' => 'The one charging ID must be a valid number. Received: ' . ($row['one_charging_id'] ?? 'null'),
            'one_charging_sync_id.exists' => 'The selected one charging sync ID does not exist in the database.',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed:', $validator->errors()->toArray());
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /**
     * Validate vouchers data for CREATE operations
     */
    protected function validateVouchersData(Collection $rows)
    {
        $vouchers = [];
        $businessTypeIds = [];

        // Get employee to check existing vouchers
        $idNumber = $this->cleanValue($rows->first()['id_number'] ?? null);
        $existingEmployee = InternalCustomer::where('id_no', $idNumber)->first();

        foreach ($rows as $index => $row) {
            // Ensure no voucher_id is provided for create operations
            $voucherId = $this->cleanValue($row['voucher_id'] ?? null);
            if (!empty($voucherId)) {
                throw new \Exception("Voucher ID should be empty for new records at row " . ($index + 1) . ". To update existing vouchers, all rows must have Voucher IDs.");
            }

            $businessTypeId = $this->cleanValue($row['business_type_id'] ?? null);

            // If business_type_id is empty or is a formula, try to extract from business_type column
            if (empty($businessTypeId) || strpos($businessTypeId, '=') === 0) {
                $businessTypeText = $this->cleanValue($row['business_type'] ?? null);
                if ($businessTypeText && preg_match('/^(\d+)\s*-/', $businessTypeText, $matches)) {
                    $businessTypeId = (int) $matches[1];
                }
            } else {
                $businessTypeId = (int) $businessTypeId;
            }

            $amount = $this->cleanAmount($row['amount'] ?? null);

            // Validate individual voucher
            $validator = Validator::make([
                'business_type_id' => $businessTypeId,
                'amount' => $amount,
            ], [
                'business_type_id' => 'required|integer|exists:business_types,id',
                'amount' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                throw new \Exception("Voucher validation failed at row " . ($index + 1) . ": " . $validator->errors()->first());
            }

            // Check for duplicate business_type_id in the import file
            if (in_array($businessTypeId, $businessTypeIds)) {
                throw new \Exception("Duplicate business_type_id {$businessTypeId} found for employee at row " . ($index + 1));
            }

            // Check if employee already has a voucher with this business_type_id
            if ($existingEmployee) {
                $existingVoucher = Voucher::where('customer_id', $existingEmployee->id)
                    ->where('customer_type', InternalCustomer::class)
                    ->where('business_type_id', $businessTypeId)
                    ->first();

                if ($existingVoucher) {
                    // Get business type name for better error message
                    $businessType = \App\Models\BusinessType::find($businessTypeId);
                    $businessTypeName = $businessType ? $businessType->name : $businessTypeId;

                    throw new \Exception(
                        "Employee {$idNumber} already has an existing voucher (ID: {$existingVoucher->id}) " .
                            "for Business Type '{$businessTypeName}' (ID: {$businessTypeId}) with status '{$existingVoucher->status}'. " .
                            "Please use the Voucher ID column to update the existing voucher instead of creating a new one."
                    );
                }
            }

            $businessTypeIds[] = $businessTypeId;
            $vouchers[] = $validator->validated();
        }

        if (empty($vouchers)) {
            throw new \Exception("At least one voucher is required");
        }

        return $vouchers;
    }

    /**
     * Clean and trim values
     */
    protected function cleanValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * Clean amount (remove commas and convert to integer)
     */
    protected function cleanAmount($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Remove commas and any whitespace
        $cleaned = str_replace([',', ' '], '', trim($value));

        return is_numeric($cleaned) ? (int) $cleaned : null;
    }

    /**
     * Get import results
     */
    public function getResults()
    {
        return [
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'errors' => $this->errors,
        ];
    }

    /**
     * Heading row number
     */
    public function headingRow(): int
    {
        return 2; // Row 2 contains the actual headers
    }

    /**
     * Start row for data
     */
    public function startRow(): int
    {
        return 3; // Data starts at row 3
    }
}
