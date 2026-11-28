<?php

namespace App\Http\Controllers\API\Admin;

use App\Enums\Legal\Type;
use App\Http\Controllers\Controller;
use App\Http\Requests\Legal\LegalDocumentRequest;
use App\Http\Resources\Legal\LegalDocumentResource;
use App\Models\LegalDocument;

class AdminLegalDocumentController extends Controller
{
    /**
     * List every legal document type, including ones not written yet,
     * so the admin can see what still needs filling in.
     */
    public function index()
    {
        $documents = LegalDocument::all()->keyBy(fn (LegalDocument $document) => $document->type->value);

        $data = collect(Type::cases())->map(function (Type $type) use ($documents) {
            $document = $documents->get($type->value);

            return $document
                ? (new LegalDocumentResource($document))->resolve()
                : [
                    'id' => null,
                    'type' => $type->value,
                    'type_label' => $type->label(),
                    'title' => $type->label(),
                    'content' => '',
                    'created_at' => null,
                    'updated_at' => null,
                ];
        })->values();

        return $this->handleSuccessResponse('Legal documents fetched successfully', $data);
    }

    public function update(LegalDocumentRequest $request, Type $type)
    {
        $document = LegalDocument::updateOrCreate(
            ['type' => $type],
            $request->validated()
        );

        return $this->handleSuccessResponse(
            'Legal document saved successfully',
            new LegalDocumentResource($document)
        );
    }
}
