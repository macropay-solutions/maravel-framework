<?php

namespace Illuminate\Testing\Constraints;

use PHPUnit\Framework\Constraint\Constraint;
use ReflectionClass;

class SeeInOrder extends Constraint
{
    /**
     * The string under validation.
     *
     * @var string
     */
    protected $content;

    /**
     * The last value that failed to pass validation.
     *
     * @var string
     */
    protected $failedValue;

    /**
     * Create a new constraint instance.
     *
     * @param  string  $content
     * @return void
     */
    public function __construct($content)
    {
        $this->content = $content;
    }

    /**
     * Determine if the rule passes validation.
     *
     * @param  array  $other
     * @return bool
     */
    public function matches($other): bool
    {
        $position = 0;

        foreach ($other as $value) {
            if (empty($value)) {
                continue;
            }

            $valuePosition = mb_strpos($this->content, $value, $position);

            if ($valuePosition === false || $valuePosition < $position) {
                $this->failedValue = $value;

                return false;
            }

            $position = $valuePosition + mb_strlen($value);
        }

        return true;
    }

    /**
     * Get the description of the failure.
     *
     * @param  array  $other
     * @return string
     */
    public function failureDescription($other): string
    {
        return sprintf(
            'Failed asserting that \'%s\' contains "%s" in specified order.',
            $this->content,
            $this->failedValue
        );
    }

    /**
     * Get a string representation of the object.
     *
     * @return string
     */
    public function toString(): string
    {
        return (new ReflectionClass($this))->name;
    }
}
