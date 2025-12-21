<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\CountryRepositoryInterface;
use App\Http\Resources\Country\CountryResource;
use App\Http\Resources\Rate\RateResource;
use App\Http\Traits\ResponseTrait;
use App\Models\Country;

class CountryRepository implements CountryRepositoryInterface {

    use ResponseTrait;

    public Country $model;

    public function __construct(Country $model)
    {
        $this->model = $model;
    }

    public function index() {
        $countries  = $this->model->when(request()->query('iso2'), function($query){
            $query->where("iso2",request()->query('iso2'));
        })->get();
        return $this->handleSuccessCollectionResponse("Successfully fetched countries", CountryResource::collection($countries));
    }




}