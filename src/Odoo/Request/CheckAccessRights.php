<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Request;

class CheckAccessRights extends Request
{
    public function __construct(
        string $model,
        private readonly string $permission,
    ) {
        parent::__construct($model, 'check_access_rights');
    }

    public function toArray(): array
    {
        return [$this->permission];
    }
}
