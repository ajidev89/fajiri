<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\UserRepositoryInterface;  

class UserController extends Controller
{

    public function __construct(protected UserRepositoryInterface $userRepositoryInterface)
    {}

    public function index(){
        return $this->userRepositoryInterface->index();
    }

}
