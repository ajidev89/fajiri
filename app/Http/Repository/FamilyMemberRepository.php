<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\FamilyMemberRepositoryInterface;
use App\Models\FamilyMember;

class FamilyMemberRepository implements FamilyMemberRepositoryInterface
{
    public function __construct(protected FamilyMember $familyMember)
    {
    }

    public function all($userId)
    {
        return $this->familyMember->where('user_id', $userId)
            ->with(['children', 'parent'])
            ->latest()
            ->get();
    }

    public function find($id)
    {
        return $this->familyMember->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->familyMember->create($data);
    }

    public function update($id, array $data)
    {
        $member = $this->find($id);
        $member->update($data);
        return $member;
    }

    public function delete($id)
    {
        $member = $this->find($id);
        return $member->delete();
    }
}
