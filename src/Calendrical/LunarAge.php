<?php

declare(strict_types=1);

namespace Directorium\Core\Calendrical;

use DateTimeImmutable;
use DateTimeZone;
use Directorium\Core\Temporal\Computus;

/**
 * The age of the ecclesiastical moon on a date — the "Luna" printed against each
 * day of the Martyrologium Romanum — reckoned 1 (the new moon) upward.
 *
 * This is the schematic moon of the computus, not the astronomical one: its
 * lunations are whole days, full (30) and hollow (29) alternating, anchored to
 * the paschal new moon of the date's year. So the fourteenth day of the paschal
 * lunation (Luna 14) coincides exactly, every year, with the ecclesiastical full
 * moon from which Easter is reckoned — the anchor is {@see Computus::paschalFullMoon()}
 * itself — and the age is exact through the whole liturgical year around it.
 * Reconciling the last lunation across the civil-year boundary (the embolismic
 * month and the once-in-nineteen-years saltus lunae) is a refinement noted in
 * KNOWN-LIMITATIONS; within a resolved year the age is continuous.
 *
 * The year-level cyclic numbers (Golden Number, Epact, …) live in
 * {@see CalendricalYear}; the epact is the moon's age at the head of the year,
 * this is its age on any given day.
 */
final class LunarAge
{
    /** A full lunation runs 30 days, a hollow one 29; the paschal lunation is full. */
    private const FULL_LUNATION = 30;

    private const HOLLOW_LUNATION = 29;

    /** Days from the new moon (Luna 1) to the full moon (Luna 14). */
    private const FULL_MOON_OFFSET = 13;

    private int $age;

    private function __construct(int $age)
    {
        $this->age = $age;
    }

    public static function onDate(DateTimeImmutable $date): self
    {
        $midnight = (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))
            ->setDate((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));

        // The paschal new moon (Luna 1) of the date's civil year: the full moon
        // less its thirteen days. It opens a full (30-day) lunation, and the
        // lunations alternate full/hollow either side of it.
        $paschalNewMoon = Computus::paschalFullMoon((int) $date->format('Y'))
            ->modify(sprintf('-%d days', self::FULL_MOON_OFFSET));

        $offset = intdiv($midnight->getTimestamp() - $paschalNewMoon->getTimestamp(), 86400);

        return new self(self::ageAtOffset($offset));
    }

    /**
     * The moon's age at $offset days from the paschal new moon (0 = Luna 1),
     * walking whole lunations that alternate full (30) and hollow (29): forward
     * from the paschal lunation 30, 29, 30, …; backward 29, 30, 29, ….
     */
    private static function ageAtOffset(int $offset): int
    {
        if ($offset >= 0) {
            $start = 0;
            for ($i = 0;; $i++) {
                $length = $i % 2 === 0 ? self::FULL_LUNATION : self::HOLLOW_LUNATION;
                if ($offset < $start + $length) {
                    return $offset - $start + 1;
                }
                $start += $length;
            }
        }

        $end = 0;
        for ($i = 0;; $i++) {
            $length = $i % 2 === 0 ? self::HOLLOW_LUNATION : self::FULL_LUNATION;
            $lunationStart = $end - $length;
            if ($offset >= $lunationStart) {
                return $offset - $lunationStart + 1;
            }
            $end = $lunationStart;
        }
    }

    /** The moon's age, 1 (new moon) … 30. */
    public function age(): int
    {
        return $this->age;
    }

    /** True on Luna 14, the ecclesiastical full moon. */
    public function isFullMoon(): bool
    {
        return $this->age === self::FULL_MOON_OFFSET + 1;
    }

    /** True on Luna 1, the ecclesiastical new moon. */
    public function isNewMoon(): bool
    {
        return $this->age === 1;
    }

    /**
     * A coarse phase label for the moon's age: the four principal phases and the
     * crescent/gibbous quarters between them.
     */
    public function phase(): string
    {
        switch (true) {
            case $this->age === 1:
                return 'new';
            case $this->age < 8:
                return 'waxing-crescent';
            case $this->age === 8:
                return 'first-quarter';
            case $this->age < 14:
                return 'waxing-gibbous';
            case $this->age === 14:
                return 'full';
            case $this->age < 22:
                return 'waning-gibbous';
            case $this->age === 22:
                return 'last-quarter';
            default:
                return 'waning-crescent';
        }
    }

    public function equals(self $other): bool
    {
        return $this->age === $other->age;
    }
}
