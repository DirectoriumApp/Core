<?php

declare(strict_types=1);

namespace Introibo\Core\Contract;

/**
 * The particular calendar a day was resolved under, as it appears in the output
 * contract's `calendar` block (#78): the overlay's platform URN and display name.
 *
 * A day resolved under the universal 1962 calendar has no descriptor — the contract's
 * `calendar` block stays null there, so the default output shape (and the golden
 * digest that hashes it) is unmoved. It is only non-null when a caller selects a
 * particular calendar (SSPX, FSSP, …), telling a consumer which calendar produced the
 * day. The overlay is *also* reflected in `corpusVersion` (`base+overlayId`); this is
 * the structured, human-readable counterpart for display.
 *
 * Immutable: two invariant strings.
 */
final class CalendarDescriptor
{
    private string $id;

    private string $name;

    public function __construct(string $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    /** The overlay's platform URN, e.g. `introibo:overlay:roman:sspx`. */
    public function id(): string
    {
        return $this->id;
    }

    /** The overlay's display name, e.g. `Society of Saint Pius X`. */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array{id: string, name: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
