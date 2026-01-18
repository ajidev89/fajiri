<?php

namespace App\Enums\Document;

use App\Http\Traits\EnumTrait;

enum Type: string
{
    use EnumTrait;
    
    case FRONT = 'document-front';
    case BACK = 'document-back';
    case FACE = 'face';

}