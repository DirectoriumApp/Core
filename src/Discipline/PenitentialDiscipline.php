<?php

declare(strict_types=1);

namespace Directorium\Core\Discipline;

use Directorium\Core\Corpus\Corpus;

/**
 * A penitential discipline read from the corpus (`disciplines/<key>/`): the law of
 * fast and abstinence as cited data, on its own axis.
 *
 * Fasting is canon law, not rubric, so a single discipline governs several rubric
 * editions ({@see \Directorium\Core\Edition\RubricSystem::penitentialDiscipline()}
 * maps each to its key — all traditional editions to the 1917 Code). This class is the
 * read seam: it exposes each named rule's obligation and the set of vigils that carry a
 * fast, which {@see FastingResolver} applies to a resolved day. Adding a later discipline
 * (Paenitemini, the modern norms) is a new data folder and a new key — no engine change.
 */
final class PenitentialDiscipline
{
    private string $urn;

    private string $name;

    /** @var array<string, array{fast: bool, abstinence: Abstinence, cite: string}> Keyed by rule name. */
    private array $rules;

    /** @var array<string, true> The vigil ids that carry a fast, as a lookup set. */
    private array $fastingVigilIds;

    /**
     * @param array<string, array{fast: bool, abstinence: Abstinence, cite: string}> $rules
     * @param array<string, true>                                                    $fastingVigilIds
     */
    private function __construct(string $urn, string $name, array $rules, array $fastingVigilIds)
    {
        $this->urn = $urn;
        $this->name = $name;
        $this->rules = $rules;
        $this->fastingVigilIds = $fastingVigilIds;
    }

    public static function fromCorpus(Corpus $corpus, string $key): self
    {
        $meta = $corpus->disciplineMeta($key);

        $rules = [];
        $fastingVigilIds = [];
        foreach ($corpus->fastingRules($key) as $row) {
            $name = (string) $row['rule'];
            $rules[$name] = [
                'fast' => (bool) $row['fast'],
                'abstinence' => Abstinence::fromString((string) $row['abstinence']),
                'cite' => (string) $row['cite'],
            ];
            if ($name === 'vigil' && isset($row['vigils']) && is_array($row['vigils'])) {
                foreach ($row['vigils'] as $id) {
                    $fastingVigilIds[(string) $id] = true;
                }
            }
        }

        return new self((string) $meta['urn'], (string) $meta['name'], $rules, $fastingVigilIds);
    }

    /** The discipline's stable URN, e.g. `roman:cic-1917`. */
    public function urn(): string
    {
        return $this->urn;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function definesRule(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    /**
     * The obligation the named rule carries, or null when this discipline declares no
     * such rule (so a later, laxer discipline simply omits the rules it dropped).
     *
     * @return array{fast: bool, abstinence: Abstinence, cite: string}|null
     */
    public function rule(string $name): ?array
    {
        return $this->rules[$name] ?? null;
    }

    /** Whether the given vigil-day id is one this discipline keeps as a fast day. */
    public function isFastingVigil(string $id): bool
    {
        return isset($this->fastingVigilIds[$id]);
    }
}
