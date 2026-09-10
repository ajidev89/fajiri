<?php

namespace App\Http\Repository;

use App\Enums\Family\Relationship;
use App\Http\Repository\Contracts\FamilyMemberRepositoryInterface;
use App\Models\FamilyMember;

class FamilyMemberRepository implements FamilyMemberRepositoryInterface
{
    public function __construct(protected FamilyMember $familyMember) {}

    public function all($userId)
    {
        return $this->familyMember->where('user_id', $userId)
            ->with(['children', 'parent'])
            ->latest()
            ->get();
    }

    public function adminAll($request)
    {
        return $this->familyMember->with(['children', 'parent', 'user'])
            ->latest()
            ->paginate($request->per_page ?? 15);
    }

    public function find($id)
    {
        return $this->familyMember->findOrFail($id);
    }

    public function create(array $data)
    {
        if (empty($data['parent_id'])) {
            $data['parent_id'] = $this->familyMember
                ->where('user_id', $data['user_id'])
                ->where('relationship', Relationship::ME->value)
                ->value('id');
        }

        return $this->familyMember->create($data);
    }

    public function update($id, array $data)
    {
        $member = $this->find($id);

        if ($member->relationship === Relationship::ME->value) {
            $data['relationship'] = Relationship::ME->value;
            $data['parent_id'] = null;
        }

        $member->update($data);

        return $member;
    }

    public function delete($id)
    {
        $member = $this->find($id);

        return $member->delete();
    }
}
