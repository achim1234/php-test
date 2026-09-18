<?php

declare(strict_types=1);

namespace App\Enum;

enum ColorProcess: string
{
    case None = 'none';
    case Grayscale = 'grayscale';
    case Sepia = 'sepia';
    case Vintage = 'vintage';
    case Dramatic = 'dramatic';
    case Neon = 'neon';
    case Solarize = 'solarize';
    case Thermal = 'thermal';
    case Toxic = 'toxic';
    case Posterize = 'posterize';
    case GameBoy = 'gameboy';
    case ChromaticHalftone = 'chromatic_halftone';
    case AchimsSpecial = 'achims_special';
    case Duotone = 'duotone';
}
