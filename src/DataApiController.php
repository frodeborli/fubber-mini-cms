<?php

namespace MiniCms;

use mini\Controller\AbstractController;
use mini\Http\Message\HtmlResponse;
use mini\Http\Message\JsonResponse;
use mini\Mini;
use Psr\Http\Message\ResponseInterface;

class DataApiController extends AbstractController
{
    private string $slug;
    private Entity $entity;

    public function __construct(string $slug)
    {
        parent::__construct();

        $this->slug = $slug;
        $content = Mini::$mini->get(Content::class);
        $models = $content->models();
        if (!isset($models[$slug])) {
            throw new \mini\Exceptions\NotFoundException("Unknown entity: $slug");
        }
        $this->entity = $models[$slug];

        $this->router->get('/', $this->list(...));
        $this->router->get('/{id}', $this->lookup(...));
        $this->router->get('/{id}/inline', $this->inline(...));
    }

    public function list(): ResponseInterface
    {
        $this->requireAuth();

        $entity = $this->entity;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int)($_GET['perPage'] ?? 25)));
        $search = trim($_GET['search'] ?? '');
        $sort = $_GET['sort'] ?? null;
        $dir = strtolower($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

        $query = $entity->query();

        if ($search !== '') {
            $fields = $entity->getFormFields();
            $searchable = [];
            $pattern = '%' . $search . '%';
            foreach ($entity->getListColumns() as $col) {
                $type = $fields[$col]['type'] ?? 'text';
                if (in_array($type, ['text', 'textarea', 'email', 'url'], true)) {
                    $searchable[] = $col;
                }
            }
            if (count($searchable) >= 2) {
                $predicates = array_map(fn($col) => \mini\p->like($col, $pattern), $searchable);
                $query = $query->or($predicates[0], $predicates[1], ...array_slice($predicates, 2));
            } elseif (count($searchable) === 1) {
                $query = $query->like($searchable[0], $pattern);
            }
        }

        $total = $query->count();

        if ($sort && in_array($sort, $entity->getListColumns(), true)) {
            $query = $query->order("$sort $dir");
        } elseif ($entity->getDefaultOrder()) {
            $query = $query->order($entity->getDefaultOrder());
        }

        $query = $query->limit($perPage)->offset(($page - 1) * $perPage);

        $pk = $entity->getPrimaryKeyName();
        $columns = $entity->getListColumns();
        $rows = [];

        foreach ($query as $item) {
            $row = ['_id' => $item->$pk, '_display' => $entity->getDisplayValue($item)];
            foreach ($columns as $col) {
                $val = $item->$col ?? '';
                if ($val instanceof \DateTimeInterface) {
                    $val = \mini\Fmt::dateShort($val);
                } elseif (is_bool($val)) {
                    $val = $val ? 'Yes' : 'No';
                }
                $val = (string)$val;
                if (strlen($val) > 80) {
                    $val = substr($val, 0, 80) . '...';
                }
                $row[$col] = $val;
            }
            $rows[] = $row;
        }

        return new JsonResponse([
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    public function lookup(int $id): ResponseInterface
    {
        $this->requireAuth();

        $item = $this->entity->find($id);
        if (!$item) {
            return new JsonResponse(['error' => 'Not found'], [], 404);
        }

        $pk = $this->entity->getPrimaryKeyName();
        return new JsonResponse([
            'id' => $item->$pk,
            'display' => $this->entity->getDisplayValue($item),
        ]);
    }

    public function inline(int $id): ResponseInterface
    {
        $this->requireAuth();

        $item = $this->entity->find($id);
        if (!$item) {
            return new HtmlResponse('<span class="text-muted">Not found</span>', 404);
        }

        return new HtmlResponse($this->entity->renderInline($item));
    }

    private function requireAuth(): void
    {
        if (empty($_SESSION['cms_user'])) {
            throw new \mini\Exceptions\AuthenticationRequiredException();
        }
    }
}
