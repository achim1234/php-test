<?php

declare(strict_types=1);

namespace App\Enum;

enum DistortionMode: string
{
    case Signal = 'signal';
    case DataMosh = 'datamosh';
    case Melt = 'melt';
    case Mirror = 'mirror';
    case Vhs = 'vhs';
    case Shred = 'shred';
}
