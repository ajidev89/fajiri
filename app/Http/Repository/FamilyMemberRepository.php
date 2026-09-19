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
        $query = $this->familyMember->with(['children', 'parent', 'user']);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('relationship', 'like', "%{$term}%")
                    ->orWhere('added_by', 'like', "%{$term}%");
            });
        }

        if ($request->filled('gender') && $request->gender !== 'all') {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'alive') {
                $query->where('is_alive', true);
            } elseif (in_array($request->status, ['deceased', 'inactive'], true)) {
                $query->where('is_alive', false);
            }
        }

        $sortBy = in_array($request->input('sort_by'), ['created_at', 'updated_at', 'full_name'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        return $query->orderBy($sortBy, $sortOrder)
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
