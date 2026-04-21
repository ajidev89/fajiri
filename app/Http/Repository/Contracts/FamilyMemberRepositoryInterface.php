<?php

namespace App\Http\Repository\Contracts;

interface FamilyMemberRepositoryInterface
{
    public function all($userId);

    public function find($id);

    public function create(array $data);

    public function update($id, array $data);

    public function delete($id);
}
