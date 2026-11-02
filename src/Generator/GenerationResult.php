<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

/**
 * Counts of entities actually created during a generator run, plus free-form messages.
 */
final class GenerationResult
{
    /** @var array<string, int> */
    private array $created = [];
    /** @var string[] */
    private array $messages = [];

    public function add(string $entity, int $count = 1): self
    {
        if (!isset($this->created[$entity])) {
            $this->created[$entity] = 0;
        }
        $this->created[$entity] += $count;

        return $this;
    }

    public function addMessage(string $message): self
    {
        $this->messages[] = $message;

        return $this;
    }

    public function merge(self $other): self
    {
        foreach ($other->getCreated() as $entity => $count) {
            $this->add($entity, $count);
        }
        foreach ($other->getMessages() as $message) {
            $this->addMessage($message);
        }

        return $this;
    }

    /**
     * @return array<string, int>
     */
    public function getCreated(): array
    {
        return $this->created;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getTotal(): int
    {
        return (int) array_sum($this->created);
    }
}
