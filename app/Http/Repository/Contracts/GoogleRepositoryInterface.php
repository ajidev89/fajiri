<?php
namespace App\Http\Repository\Contracts;
 
use Illuminate\Http\Request;

interface GoogleRepositoryInterface
{
    public function generateGoogleUrl(Request $request);

    public function handleGoogleCallback(Request $request);
}