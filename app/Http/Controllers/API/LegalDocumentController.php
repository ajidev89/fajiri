<?php

namespace App\Http\Controllers\API;

use App\Enums\Legal\Type;
use App\Http\Controllers\Controller;
use App\Http\Resources\Legal\LegalDocumentResource;
use App\Models\LegalDocument;

class LegalDocumentController extends Controller
{
    public function show(Type $type)
    {
        $document = LegalDocument::where('type', $type)->firstOrFail();

        return $this->handleSuccessResponse(
            'Legal document fetched successfully',
            (new LegalDocumentResource($document))->resolve()
        );
    }
}
