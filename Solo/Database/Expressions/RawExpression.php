<?php declare(strict_types=1);

namespace Solo\Database\Expressions;

final class RawExpression
{
    private string $expression;

    public function __construct(string $expression)
{
        $this->expression = $expression;
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}