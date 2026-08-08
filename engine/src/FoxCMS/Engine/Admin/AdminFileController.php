<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Admin;

final class AdminFileController
{
    public function __construct(
        private array $request,
        private \AdminFileManager $files,
        private \AdminResponder $responder,
    ) {
    }

    public function list(): never
    {
        $this->responder->send($this->files->browse((string)($this->request['path'] ?? '')));
    }

    public function createDirectory(): never
    {
        $this->responder->send($this->files->createDirectory(
            (string)($this->request['path'] ?? ''),
            (string)($this->request['name'] ?? ''),
        ));
    }

    public function upload(): never
    {
        $this->responder->send($this->files->upload(
            (string)($this->request['path'] ?? ''),
            is_array($this->request['_upload'] ?? null) ? $this->request['_upload'] : null,
        ), 201);
    }

    public function rename(): never
    {
        $this->responder->send($this->files->rename(
            (string)($this->request['path'] ?? ''),
            (string)($this->request['name'] ?? ''),
        ));
    }

    public function delete(): never
    {
        $this->responder->send($this->files->delete((string)($this->request['path'] ?? '')));
    }
}
