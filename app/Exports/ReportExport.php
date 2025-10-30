<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;
    protected $flattenedData = [];

    public function __construct($data)
    {
        $this->data = $data;
        $this->flattenData();
    }

    /**
     * Flatten the data to create rows for each referral history
     */
    private function flattenData()
    {
        foreach ($this->data as $referral) {
            $referralHistories = $referral['referral_histories'] ?? [];
            $statusName = $referral['referral']['status_name'] ?? '';

            if (empty($referralHistories)) {
                // If no histories, create one row with referral data only
                $this->flattenedData[] = [
                    'referral_id' => $referral['referral_id'],
                    'referral' => $referral['referral'],
                    'history' => null,
                    'is_first_row' => true,
                    'total_histories' => 0
                ];
            } else {
                $totalHistories = count($referralHistories);
                foreach ($referralHistories as $index => $history) {
                    $sequence = $history['sequence'] ?? 0;
                    $isLastEntry = ($index === $totalHistories - 1);

                    $this->flattenedData[] = [
                        'referral_id' => $referral['referral_id'],
                        'referral' => $referral['referral'],
                        'history' => $history,
                        'is_first_row' => $index === 0,
                        'total_histories' => $totalHistories,
                        'role' => $this->getRoleBySequence($sequence, $statusName, $isLastEntry)
                    ];
                }
            }
        }
    }

    /**
     * Get role based on sequence number, status, and position
     */
    private function getRoleBySequence($sequence, $statusName = '', $isLastEntry = false)
    {
        switch ($sequence) {
            case 1:
                return 'Initial Referrer';
            case 2:
                return 'Initial Assignee';
            default:
                if ($sequence >= 3) {
                    // If status is "Closed" and this is the last entry, return "Final Referrer"
                    if (strtolower($statusName) === 'closed' && $isLastEntry) {
                        return 'Final Referrer';
                    }
                    return 'Next Referrer';
                }
                return '';
        }
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->flattenedData);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Referral ID',
            'Customer ID',
            'Priority',
            'Status',
            'Status Note',
            'Created At',
            'Updated At',
            'Role',
            'Staff ID',
            'Business Unit',
            'Location',
            'Sequence',
            'Referral Reason',
            'Referral Condition',
            'Medical History',
            'Additional Remarks',
            'Form Completion',
            'Form Details'
        ];
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        $referral = $row['referral'] ?? [];
        $history = $row['history'] ?? [];
        $isFirstRow = $row['is_first_row'] ?? false;

        // Format referral details as a string
        $formDetails = '';
        if (!empty($history['referral_details'])) {
            $details = [];
            foreach ($history['referral_details'] as $detail) {
                $details[] = $detail['form_name'] . ': ' . $detail['value'];
            }
            $formDetails = implode(' | ', $details);
        }

        // Convert is_filled to Yes/No
        $formCompleted = '';
        if (isset($history['is_filled'])) {
            $formCompleted = $history['is_filled'] == 1 ? 'Yes' : 'No';
        }

        return [
            // Referral data (repeated on every row for easy VLOOKUP and filtering)
            $row['referral_id'],
            $referral['customer_id'] ?? '',
            $referral['priority'] ?? '',
            $referral['status_name'] ?? '',
            $referral['status_note'] ?? '',
            $referral['created_at'] ?? '',
            $referral['updated_at'] ?? '',

            // History data (show on every row)
            $row['role'] ?? '',
            $history['staff_id'] ?? '',
            $history['business_unit'] ?? '',
            $history['location'] ?? '',
            $history['sequence'] ?? '',
            $history['referral_reason'] ?? '',
            $history['referral_condition'] ?? '',
            $history['medical_history'] ?? '',
            $history['additional_remarks'] ?? '',
            $formCompleted,
            $formDetails
        ];
    }
}
