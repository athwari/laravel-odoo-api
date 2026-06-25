<?php

namespace Athwari\LaravelOdooApi\Testing;

class FakeExpectation
{
    private mixed $returnValue = null;

    public function __construct(private string $model, private string $method) {}

    public function andReturn(mixed $value): static
    {
        $this->returnValue = $value;

        return $this;
    }

    public function matches(string $model, string $method): bool
    {
        return ($this->model === '*' || $this->model === $model)
            && ($this->method === '*' || $this->method === $method);
    }

    public function resolve(): mixed
    {
        return $this->returnValue;
    }
}
