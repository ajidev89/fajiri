<?php

namespace App\Http\Controllers\API\Admin;

use App\Enums\Faq\Type;
use App\Http\Controllers\Controller;
use App\Http\Requests\Faq\FaqRequest;
use App\Http\Resources\Faq\FaqResource;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFaqController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'type' => ['nullable', Rule::enum(Type::class)],
        ]);

        $query = Faq::query();

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($builder) use ($term) {
                $builder->where('question', 'like', "%{$term}%")
                    ->orWhere('answer', 'like', "%{$term}%");
            });
        }

        $sortBy = in_array($request->input('sort_by'), ['created_at', 'updated_at', 'question', 'type', 'sort_order'], true)
            ? $request->input('sort_by')
            : 'sort_order';
        $sortOrder = in_array(strtolower((string) $request->input('sort_order', 'asc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'asc'))
            : 'asc';

        $faqs = $query->orderBy($sortBy, $sortOrder)
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 15));

        return $this->handleSuccessCollectionResponse(
            'FAQs fetched successfully',
            FaqResource::collection($faqs)
        );
    }

    public function store(FaqRequest $request)
    {
        $faq = Faq::create($request->validated());

        return $this->handleSuccessResponse(
            'FAQ created successfully',
            new FaqResource($faq)
        );
    }

    public function update(FaqRequest $request, Faq $faq)
    {
        $faq->update($request->validated());

        return $this->handleSuccessResponse(
            'FAQ updated successfully',
            new FaqResource($faq)
        );
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return $this->handleSuccessResponse('FAQ deleted successfully');
    }
}
