<?php
if (! function_exists('createRefId')) {
    function createRefId($id)
    {
        $param = '#REF';
        $updatedId = $param . str_pad($id, 4, '0', STR_PAD_LEFT);
        return $updatedId;
    }
}

if (! function_exists('getStatus')) {
    /**
     * Get status name from status number
     * Copied from ReferralController
     */
    function getStatus($ref_status)
    {
        /**
         * Status
         * 1 = Open
         * 2 = In Progress
         * 3 = Referred
         * 4 = Closed
         * 5 = Not Present
         */
        switch ($ref_status) {
            case '1':
            case 1:
                $status = 'Open';
                break;

            case '2':
            case 2:
                $status = 'In Progress';
                break;

            case '3':
            case 3:
                $status = 'Referred';
                break;

            case '4':
            case 4:
                $status = 'Closed';
                break;

            case '5':
            case 5:
                $status = 'Not Present';
                break;

            default:
                $status = 'Submitted';
                break;
        }
        return $status;
    }
}
