<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function referralStatus()
    {
        /**
         * Status
         * 1 = Open
         * 2 = In Progress
         * 3 = Forwarded
         * 4 = Closed
         */

        $status = [
            1 => 'Open',
            2 => 'In Progress',
            3 => 'Forwarded',
            4 => 'Closed',
        ];

        return $status;
    }
}
