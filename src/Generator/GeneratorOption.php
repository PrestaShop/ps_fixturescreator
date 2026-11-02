<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Generator;

/**
 * Describes a single option accepted by a fixture generator.
 * Options are exposed both on the CLI (--option key=value) and in the back office form.
 */
final class GeneratorOption
{
    public const TYPE_INT = 'int';
    public const TYPE_BOOL = 'bool';
    public const TYPE_CHOICE = 'choice';

    private string $name;
    private string $label;
    private string $type;
    /** @var int|bool|string */
    private $default;
    private string $description;
    /** @var string[] */
    private array $choices;

    /**
     * @param int|bool|string $default
     * @param string[] $choices
     */
    public function __construct(string $name, string $label, string $type, $default, string $description = '', array $choices = [])
    {
        $this->name = $name;
        $this->label = $label;
        $this->type = $type;
        $this->default = $default;
        $this->description = $description;
        $this->choices = $choices;
    }

    public static function int(string $name, string $label, int $default, string $description = ''): self
    {
        return new self($name, $label, self::TYPE_INT, $default, $description);
    }

    public static function bool(string $name, string $label, bool $default, string $description = ''): self
    {
        return new self($name, $label, self::TYPE_BOOL, $default, $description);
    }

    /**
     * @param string[] $choices
     */
    public static function choice(string $name, string $label, array $choices, string $default, string $description = ''): self
    {
        return new self($name, $label, self::TYPE_CHOICE, $default, $description, $choices);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return int|bool|string
     */
    public function getDefault()
    {
        return $this->default;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string[]
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Casts a raw value (string from CLI or request) to the option type.
     *
     * @param mixed $value
     *
     * @return int|bool|string
     */
    public function normalize($value)
    {
        switch ($this->type) {
            case self::TYPE_INT:
                return (int) $value;
            case self::TYPE_BOOL:
                if (is_string($value)) {
                    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
                }

                return (bool) $value;
            case self::TYPE_CHOICE:
                $value = (string) $value;

                return in_array($value, $this->choices, true) ? $value : (string) $this->default;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'default' => $this->default,
            'description' => $this->description,
            'choices' => $this->choices,
        ];
    }
}
