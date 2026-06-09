<?php

namespace MiniCms;

use mini\Validator\Validator;

class Entity
{
    private string $modelClass;
    private string $icon;
    private ?string $title = null;
    private ?string $pluralTitle = null;
    private ?array $listColumns = null;
    private ?string $defaultOrder = null;
    private ?string $indexView = null;
    private ?string $createView = null;
    private ?string $editView = null;
    private ?string $showView = null;
    private ?string $displayColumn = null;
    private ?string $inlineView = null;

    public function __construct(string $modelClass, string $icon = 'bi-database', ?string $title = null, ?string $pluralTitle = null)
    {
        $this->modelClass = $modelClass;
        $this->icon = $icon;
        $this->title = $title;
        $this->pluralTitle = $pluralTitle;
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getTitle(): string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $meta = \mini\metadata($this->modelClass);
        if ($meta->getTitle() !== null) {
            return (string)$meta->getTitle();
        }

        $short = (new \ReflectionClass($this->modelClass))->getShortName();
        return $short;
    }

    public function getPluralTitle(): string
    {
        if ($this->pluralTitle !== null) {
            return $this->pluralTitle;
        }
        return $this->getTitle();
    }

    public function getListColumns(): array
    {
        if ($this->listColumns !== null) {
            return $this->listColumns;
        }

        $ref = new \ReflectionClass($this->modelClass);
        $columns = [];
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($this->isNotMapped($prop)) continue;
            if ($this->isPrimaryKey($prop)) continue;
            if ($this->isTimestamp($prop)) continue;
            $columns[] = $prop->getName();
        }
        return $columns;
    }

    public function withListColumns(array $columns): static
    {
        $clone = clone $this;
        $clone->listColumns = $columns;
        return $clone;
    }

    public function getDefaultOrder(): ?string
    {
        return $this->defaultOrder;
    }

    public function withDefaultOrder(string $order): static
    {
        $clone = clone $this;
        $clone->defaultOrder = $order;
        return $clone;
    }

    public function getIndexView(): string
    {
        return $this->indexView ?? 'admin/crud/index.php';
    }

    public function getCreateView(): string
    {
        return $this->createView ?? 'admin/crud/create.php';
    }

    public function getEditView(): string
    {
        return $this->editView ?? 'admin/crud/edit.php';
    }

    public function getShowView(): string
    {
        return $this->showView ?? 'admin/crud/show.php';
    }

    public function withIndexView(string $view): static
    {
        $clone = clone $this;
        $clone->indexView = $view;
        return $clone;
    }

    public function withCreateView(string $view): static
    {
        $clone = clone $this;
        $clone->createView = $view;
        return $clone;
    }

    public function withEditView(string $view): static
    {
        $clone = clone $this;
        $clone->editView = $view;
        return $clone;
    }

    public function withShowView(string $view): static
    {
        $clone = clone $this;
        $clone->showView = $view;
        return $clone;
    }

    public function getDisplayColumn(): string
    {
        if ($this->displayColumn !== null) {
            return $this->displayColumn;
        }

        $fields = $this->getFormFields();
        foreach ($this->getListColumns() as $col) {
            $type = $fields[$col]['type'] ?? 'text';
            if (in_array($type, ['text', 'email', 'url'], true)) {
                return $col;
            }
        }

        $columns = $this->getListColumns();
        return $columns[0] ?? $this->getPrimaryKeyName();
    }

    public function withDisplayColumn(string $column): static
    {
        $clone = clone $this;
        $clone->displayColumn = $column;
        return $clone;
    }

    public function getInlineView(): ?string
    {
        return $this->inlineView;
    }

    public function withInlineView(string $view): static
    {
        $clone = clone $this;
        $clone->inlineView = $view;
        return $clone;
    }

    public function renderInline(object $item): string
    {
        if ($this->inlineView !== null) {
            return \mini\render($this->inlineView, ['item' => $item, 'entity' => $this]);
        }

        $col = $this->getDisplayColumn();
        return \mini\h((string)($item->$col ?? ''));
    }

    public function getDisplayValue(object $item): string
    {
        $col = $this->getDisplayColumn();
        return (string)($item->$col ?? '');
    }

    public function getValidator(): Validator
    {
        return \mini\validator($this->modelClass);
    }

    public function getMetadata(): \mini\Metadata\Metadata
    {
        return \mini\metadata($this->modelClass);
    }

    public function getFormFields(): array
    {
        $ref = new \ReflectionClass($this->modelClass);
        $navigationProps = $this->getNavigationProperties($ref);
        $fields = [];
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($this->isNotMapped($prop)) continue;
            if ($this->isPrimaryKey($prop)) continue;
            if ($this->isTimestamp($prop)) continue;
            if ($this->isNavigationProperty($prop)) continue;

            $name = $prop->getName();
            $type = $prop->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'string';

            $field = [
                'name' => $name,
                'type' => $this->resolveFieldType($typeName, $prop),
                'nullable' => $type ? $type->allowsNull() : true,
                'label' => $this->getFieldLabel($name),
            ];

            $fkAttrs = $prop->getAttributes(\mini\Database\Attributes\ForeignKey::class);
            if ($fkAttrs) {
                $fk = $fkAttrs[0]->newInstance();
                $navPropName = $fk->navigation;
                if ($navPropName && isset($navigationProps[$navPropName])) {
                    $field['type'] = 'entity';
                    $field['entityClass'] = $navigationProps[$navPropName];
                }
            } elseif (isset($navigationProps[$name])) {
                $field['type'] = 'entity';
                $field['entityClass'] = $navigationProps[$name];
            }

            $fields[$name] = $field;
        }
        return $fields;
    }

    public function getPrimaryKeyName(): string
    {
        $ref = new \ReflectionClass($this->modelClass);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($this->isPrimaryKey($prop)) {
                return $prop->getName();
            }
        }
        return 'id';
    }

    public function query(): \mini\Database\Query
    {
        return ($this->modelClass)::queryUnsafe();
    }

    public function find(mixed $id): ?object
    {
        return ($this->modelClass)::findUnsafe($id);
    }

    private function resolveFieldType(string $typeName, \ReflectionProperty $prop): string
    {
        if ($this->hasValidatorFormat($prop, 'email')) return 'email';
        if ($this->hasValidatorFormat($prop, 'uri')) return 'url';

        return match ($typeName) {
            'int', 'float' => 'number',
            'bool' => 'checkbox',
            'DateTimeImmutable', 'DateTime' => 'datetime',
            default => $this->isTextField($prop) ? 'textarea' : 'text',
        };
    }

    private function isTextField(\ReflectionProperty $prop): bool
    {
        $attrs = $prop->getAttributes(\mini\Validator\Attributes\MaxLength::class);
        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();
            if ($instance->max > 255) return true;
        }
        return false;
    }

    private function hasValidatorFormat(\ReflectionProperty $prop, string $format): bool
    {
        $attrs = $prop->getAttributes(\mini\Validator\Attributes\Format::class);
        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();
            if ($instance->format === $format) return true;
        }
        return false;
    }

    private function getFieldLabel(string $name): string
    {
        $meta = \mini\metadata($this->modelClass);
        $propMeta = $meta->$name;
        if ($propMeta !== null && $propMeta->getTitle() !== null) {
            return (string)$propMeta->getTitle();
        }
        return ucfirst(str_replace('_', ' ', $name));
    }

    private function isNotMapped(\ReflectionProperty $prop): bool
    {
        return !empty($prop->getAttributes(\mini\Database\Attributes\NotMapped::class));
    }

    private function isPrimaryKey(\ReflectionProperty $prop): bool
    {
        return !empty($prop->getAttributes(\mini\Database\Attributes\PrimaryKey::class));
    }

    private function isTimestamp(\ReflectionProperty $prop): bool
    {
        return !empty($prop->getAttributes(\mini\Database\Attributes\CreatedAt::class))
            || !empty($prop->getAttributes(\mini\Database\Attributes\UpdatedAt::class));
    }

    private function isNavigationProperty(\ReflectionProperty $prop): bool
    {
        $type = $prop->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return false;
        }
        return is_subclass_of($type->getName(), \mini\Database\Model::class);
    }

    private function getNavigationProperties(\ReflectionClass $ref): array
    {
        $nav = [];
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($this->isNavigationProperty($prop)) {
                $type = $prop->getType();
                $nav[$prop->getName()] = $type->getName();
            }
        }
        return $nav;
    }
}
