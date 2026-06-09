<?php

namespace MiniCms;

use mini\Controller\AbstractController;
use mini\Http\Message\HtmlResponse;
use mini\Http\Message\Response;
use mini\Mini;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CrudController extends AbstractController
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

        $this->router->get('/', $this->index(...));
        $this->router->get('/create', $this->create(...));
        $this->router->post('/', $this->store(...));
        $this->router->get('/{id}', $this->show(...));
        $this->router->get('/{id}/edit', $this->edit(...));
        $this->router->post('/{id}', $this->update(...));
        $this->router->post('/{id}/delete', $this->destroy(...));
    }

    private function renderCrud(string $contentView, array $vars = []): ResponseInterface
    {
        $vars['entity'] = $this->entity;
        $vars['slug'] = $this->slug;
        $vars['currentPath'] = '/admin/data/' . $this->slug;
        $vars['contentView'] = $contentView;

        $html = \mini\render('cms/admin-crud-shell.php', $vars);
        return new HtmlResponse($html);
    }

    public function index(): ResponseInterface
    {
        $this->requireAuth();

        return $this->renderCrud($this->entity->getIndexView(), [
            'columns' => $this->entity->getListColumns(),
            'pk' => $this->entity->getPrimaryKeyName(),
        ]);
    }

    public function create(): ResponseInterface
    {
        $this->requireAuth();

        $modelClass = $this->entity->getModelClass();
        $item = new $modelClass();

        return $this->renderCrud($this->entity->getCreateView(), [
            'item' => $item,
            'fields' => $this->entity->getFormFields(),
        ]);
    }

    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAuth();

        $data = iterator_to_array($_POST);
        $errors = $this->entity->getValidator()->isInvalid($data);

        if ($errors) {
            $modelClass = $this->entity->getModelClass();
            $item = new $modelClass();
            $this->hydrateModel($item, $data);

            return $this->renderCrud($this->entity->getCreateView(), [
                'item' => $item,
                'fields' => $this->entity->getFormFields(),
                'errors' => $errors,
            ]);
        }

        $modelClass = $this->entity->getModelClass();
        $item = new $modelClass();
        $this->hydrateModel($item, $data);
        $item->saveUnsafe();

        return new Response('', ['Location' => '/admin/data/' . $this->slug . '/'], 302);
    }

    public function show(int $id): ResponseInterface
    {
        $this->requireAuth();

        $item = $this->entity->find($id);
        if (!$item) return new Response('Not found', [], 404);

        return $this->renderCrud($this->entity->getShowView(), [
            'item' => $item,
            'fields' => $this->entity->getFormFields(),
        ]);
    }

    public function edit(int $id): ResponseInterface
    {
        $this->requireAuth();

        $item = $this->entity->find($id);
        if (!$item) return new Response('Not found', [], 404);

        return $this->renderCrud($this->entity->getEditView(), [
            'item' => $item,
            'fields' => $this->entity->getFormFields(),
        ]);
    }

    public function update(int $id, ServerRequestInterface $request): ResponseInterface
    {
        $this->requireAuth();

        $item = $this->entity->find($id);
        if (!$item) return new Response('Not found', [], 404);

        $data = iterator_to_array($_POST);
        $errors = $this->entity->getValidator()->isInvalid($data);

        if ($errors) {
            $this->hydrateModel($item, $data);
            return $this->renderCrud($this->entity->getEditView(), [
                'item' => $item,
                'fields' => $this->entity->getFormFields(),
                'errors' => $errors,
            ]);
        }

        $this->hydrateModel($item, $data);
        $item->saveUnsafe();

        return new Response('', ['Location' => '/admin/data/' . $this->slug . '/'], 302);
    }

    public function destroy(int $id): ResponseInterface
    {
        $this->requireAuth();

        $item = $this->entity->find($id);
        if (!$item) return new Response('Not found', [], 404);

        $item->deleteUnsafe();

        return new Response('', ['Location' => '/admin/data/' . $this->slug . '/'], 302);
    }

    private function hydrateModel(object $item, array $data): void
    {
        $fields = $this->entity->getFormFields();
        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, $data)) continue;
            $value = $data[$name];

            $item->$name = match ($field['type']) {
                'number', 'entity' => is_numeric($value) ? (str_contains($value, '.') ? (float)$value : (int)$value) : ($field['nullable'] ? null : 0),
                'checkbox' => !empty($value),
                default => $value,
            };
        }
    }

    private function requireAuth(): void
    {
        if (empty($_SESSION['cms_user'])) {
            throw new \mini\Exceptions\AuthenticationRequiredException();
        }
    }
}
