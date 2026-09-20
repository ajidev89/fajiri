<?php

namespace App\Http\Controllers;
use App\Http\Traits\ExportsCsv;
use App\Http\Traits\ResponseTrait;

abstract class Controller
{
    use ExportsCsv, ResponseTrait;
    //
}
