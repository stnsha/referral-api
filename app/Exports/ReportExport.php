<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;
    protected $flattenedData = [];
    protected $outletCodeMap;

    public function __construct($data, $outletCodeMap = [])
    {
        $this->data = $data;
        $this->outletCodeMap = $outletCodeMap;
        $this->flattenData();
    }

    /**
     * Resolve an outlet id to its code via the map passed in from the frontend,
     * falling back to the raw id if the outlet isn't in the map.
     */
    private function resolveOutlet($outletId)
    {
        if (empty($outletId)) {
            return '';
        }
        return $this->outletCodeMap[$outletId] ?? $this->outletCodeMap[(string)$outletId] ?? $outletId;
    }

    /**
     * Flatten the data to create rows for each referral history
     */
    private function flattenData()
    {
        foreach ($this->data as $referral) {
            $referralHistories = $referral['referral_histories'] ?? [];
            $statusName = $referral['referral']['status_name'] ?? '';

            // Outlet From = first sequence's location (where the referral originated)
            // Outlet To = latest sequence's location (current/final destination)
            $outletFrom = $referralHistories[0]['location'] ?? null;
            $outletTo = $referralHistories[count($referralHistories) - 1]['location'] ?? null;

            if (empty($referralHistories)) {
                // If no histories, create one row with referral data only
                $this->flattenedData[] = [
                    'referral_id' => $referral['referral_id'],
                    'referral' => $referral['referral'],
                    'history' => null,
                    'is_first_row' => true,
                    'total_histories' => 0,
                    'outlet_from' => $outletFrom,
                    'outlet_to' => $outletTo,
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
                        'role' => $this->getRoleBySequence($sequence, $statusName, $isLastEntry),
                        'outlet_from' => $outletFrom,
                        'outlet_to' => $outletTo,
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
            'Priority',
            'Status',
            'Status Note',
            'Created At',
            'Updated At',
            'Role',
            'Business Unit',
            'Outlet From',
            'Outlet To',
            'Referral Reason',
            'Referral Condition',
            'Medical History',
            'Diagnosis',
            'Outcome',
            'Feedback',
            'Additional Remarks',
            'Form Completion',
            'Type of Referral',
            'Reply Form'
        ];
    }

    /**
     * Join dynamic referral_details entries whose form is shown on the given
     * side ('creation' or 'reply') into a single "value | value" string
     * (just the values, no form label). Forms with display_on 'both' show
     * up on both sides.
     */
    private function joinDetailsFor($referralDetails, $side)
    {
        $lines = [];
        foreach ($referralDetails as $detail) {
            $displayOn = $detail['display_on'] ?? null;
            if ($displayOn === $side || $displayOn === 'both') {
                $lines[] = $detail['value'];
            }
        }
        return implode(' | ', $lines);
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        $referral = $row['referral'] ?? [];
        $history = $row['history'] ?? [];
        $referralDetails = $history['referral_details'] ?? [];

        $typeOfReferral = $this->joinDetailsFor($referralDetails, 'creation');
        $replyFormDetails = $this->joinDetailsFor($referralDetails, 'reply');

        // Convert is_filled to Yes/No
        $formCompleted = '';
        if (isset($history['is_filled'])) {
            $formCompleted = $history['is_filled'] == 1 ? 'Yes' : 'No';
        }

        return [
            // Referral data (repeated on every row for easy VLOOKUP and filtering)
            $row['referral_id'],
            $referral['priority'] ?? '',
            $referral['status_name'] ?? '',
            $referral['status_note'] ?? '',
            $referral['created_at'] ?? '',
            $referral['updated_at'] ?? '',

            // History data (show on every row)
            $row['role'] ?? '',
            $history['business_unit'] ?? '',
            $this->resolveOutlet($row['outlet_from'] ?? null),
            $this->resolveOutlet($row['outlet_to'] ?? null),
            $history['create_form']['referral_reason'] ?? '',
            $history['create_form']['referral_condition'] ?? '',
            $history['create_form']['medical_history'] ?? '',
            $history['reply_form']['post_diagnosis'] ?? '',
            $history['reply_form']['outcome'] ?? '',
            $history['reply_form']['feedback'] ?? '',
            $history['additional_remarks'] ?? '',
            $formCompleted,
            $typeOfReferral,
            $replyFormDetails
        ];
    }
}
