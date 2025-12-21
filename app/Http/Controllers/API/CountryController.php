<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\CountryRepositoryInterface;

class CountryController extends Controller
{
    public CountryRepositoryInterface $countryRepositoryInterface;

    public function __construct(CountryRepositoryInterface $countryRepositoryInterface)
    {
        $this->countryRepositoryInterface = $countryRepositoryInterface;
    }

    public function index(){
        return $this->countryRepositoryInterface->index();
    }

}
