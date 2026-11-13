<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\SuperAdmin\Construction\FuelController as SuperAdminFuelController;

class FuelController extends SuperAdminFuelController
{
    // Inherits scoped index, show, verify, and exportCsv from base controller with automatic tenant actor resolution
}

