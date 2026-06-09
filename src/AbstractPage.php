<?php

namespace MiniCms;

use mini\Http\ResponseAggregate;
use mini\Mini;

abstract class AbstractPage implements ResponseAggregate
{
    protected array $routeVars = [];
    protected bool $visibleInNav = true;

    abstract public function getTitle(): string;

    public function isVisibleInNav(): bool
    {
        return $this->visibleInNav;
    }

    public function withNavVisibility(bool $visible): static
    {
        $clone = clone $this;
        $clone->visibleInNav = $visible;
        return $clone;
    }

    public function withRouteVariables(array $params): static
    {
        $clone = clone $this;
        $clone->routeVars = $params;
        return $clone;
    }

    public function matchesPath(string $path): bool
    {
        $content = Mini::$mini->get(Content::class);
        $resolved = $content->resolve($path);
        if ($resolved === null) return false;
        return $this->isSamePage($resolved);
    }

    protected function isSamePage(AbstractPage $other): bool
    {
        return get_class($this) === get_class($other)
            && $this->routeVars === $other->routeVars;
    }
}
