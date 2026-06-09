<?php

namespace MiniCms;

use mini\Controller\AbstractController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ComponentController extends AbstractController
{
    public function __construct()
    {
        parent::__construct();

        $this->router->put('/', $this->update(...));
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        if (empty($_SESSION['cms_user'])) {
            throw new \mini\Exceptions\AuthenticationRequiredException();
        }

        $data = json_decode((string)$request->getBody(), true);
        if (!$data || empty($data['file']) || empty($data['path']) || !isset($data['value'])) {
            throw new \mini\Exceptions\BadRequestException('Missing required fields: file, path, value');
        }

        $store = \mini\Mini::$mini->get(ContentStore::class);
        $store->write($data['file'], $data['path'], $data['type'] ?? 'text', (int)($data['pos'] ?? 0), $data['value']);

        return $this->json(['ok' => true]);
    }
}
