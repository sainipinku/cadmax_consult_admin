<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\SuperAdmin\Construction\AttendanceController as SuperAdminAttendanceController;

class AttendanceController extends SuperAdminAttendanceController
{
    // Inherits scoped index, show, review, and export from base controller with automatic actor resolution
}

