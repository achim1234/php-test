<?php

namespace Classes;

class Math
{
    /**
     * Check if a number is even
     *
     * @param int $number
     * @return bool
     */
    public function isEven(int $number): bool
    {
        return $number % 2 === 0;
    }

    /**
     * Check if a number is odd
     *
     * @param int $number
     * @return bool
     */
    public function isOdd(int $number): bool
    {
        return $number % 2 !== 0;
    }

    /**
     * Get Pi with 100 decimal places
     *
     * @return string
     */
    public function getPi(): string
    {
        return '3.1415926535897932384626433832795028841971693993751058209749445923078164062862089986280348253421170679';
    }

    /**
     * Calculate the area of a rectangle
     *
     * @param float $a
     * @param float $b
     * @return float
     */
    public function calculateRectangleArea(float $a, float $b): float
    {
        return $a * $b;
    }
}
