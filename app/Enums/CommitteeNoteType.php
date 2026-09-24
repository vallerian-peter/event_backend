<?php

namespace App\Enums;

enum CommitteeNoteType: string
{
    case Note = 'note';
    case Todo = 'todo';
    case Decision = 'decision';
}
