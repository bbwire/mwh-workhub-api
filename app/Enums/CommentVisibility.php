<?php

namespace App\Enums;

enum CommentVisibility: string
{
    case Public = 'public';
    case Internal = 'internal';
}
