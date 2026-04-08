<?php

namespace App\Enums\Blog;

use App\Http\Traits\EnumTrait;

enum PostStatus: string
{
    use EnumTrait;

    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
