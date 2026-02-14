<?php
namespace App\Http\Repository\Contracts;

interface GoogleRepositoryInterface
{
    public function generateGoogleUrl();

    public function handleGoogleCallback();
}