<?php

namespace Athwari\LaravelOdooApi\Events;

use Athwari\LaravelOdooApi\Odoo\OdooModel;

class OdooRecordDeleted
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly OdooModel $model,
    ) {}
}
