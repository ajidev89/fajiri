<?php

namespace App\Http\Controllers\API;

use App\Enums\Faq\Type;
use App\Http\Controllers\Controller;
use App\Http\Resources\Faq\FaqResource;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FaqController extends Controller
{
    public function types()
    {
        $types = collect(Type::cases())->map(fn (Type $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->values();

        return $this->handleSuccessResponse('FAQ types fetched successfully', $types);
    }

    public function index(Request $request)
    {
        $request->validate([
            'type' => ['nullable', Rule::enum(Type::class)],
        ]);

        $faqs = Faq::query()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return $this->handleSuccessResponse(
            'FAQs fetched successfully',
            FaqResource::collection($faqs)->resolve()
        );
    }
}
