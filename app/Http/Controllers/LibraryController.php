<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function displayStatus()
    {
        $data = [
            '1' => 'Open',
            '2' => 'In Progress',
            '3' => 'Referred',
            '4' => 'Closed',
            '5' => 'Not Present',
        ];

        return response()->json($data, 200);
    }

    public function displayPriority()
    {
        $data = [
            '1' => 'Low',
            '2' => 'Medium',
            '3' => 'High',
        ];

        return response()->json($data, 200);
    }
}
