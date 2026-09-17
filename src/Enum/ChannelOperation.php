<?php

declare(strict_types=1);

namespace App\Enum;

enum ChannelOperation: string
{
    case None = 'none';
    case RedOnly = 'red_only';
    case GreenOnly = 'green_only';
    case BlueOnly = 'blue_only';
    case InvertRed = 'invert_red';
    case InvertGreen = 'invert_green';
    case InvertBlue = 'invert_blue';
    case RemoveRed = 'remove_red';
    case RemoveGreen = 'remove_green';
    case RemoveBlue = 'remove_blue';
    case SwapRedGreen = 'swap_red_green';
    case SwapRedBlue = 'swap_red_blue';
    case SwapGreenBlue = 'swap_green_blue';
    case RotateRgb = 'rotate_rgb';
    case RotateRbg = 'rotate_rbg';
}
