<?php

declare(strict_types=1);

namespace Introibo\Core\Calendar;

/**
 * A realized office paired with the role it plays on a resolved day.
 *
 * This is how an office is "marked" as the celebration, a commemoration, or
 * displaced. Because it keeps the whole {@see RealizedObservance}, a displaced
 * office retains all the data — id, rank, colour, name — the transfer queue
 * (#34) needs to re-place it on a later day, and a commemoration keeps
 * everything needed to render it.
 *
 * Assigning the role is the resolver's decision (#29); this type only carries
 * it. Immutable: it holds a realized office and a role value object.
 */
final class RoledObservance
{
    private RealizedObservance $observance;

    private CelebrationRole $role;

    public function __construct(RealizedObservance $observance, CelebrationRole $role)
    {
        $this->observance = $observance;
        $this->role = $role;
    }

    public function observance(): RealizedObservance
    {
        return $this->observance;
    }

    public function role(): CelebrationRole
    {
        return $this->role;
    }

    public function equals(self $other): bool
    {
        return $this->role->equals($other->role)
            && $this->observance->id()->equals($other->observance->id())
            && $this->observance->rank()->equals($other->observance->rank())
            && $this->observance->colour()->equals($other->observance->colour())
            && $this->observance->latinName() === $other->observance->latinName();
    }
}
