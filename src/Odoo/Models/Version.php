<?php

declare(strict_types=1);

namespace Athwari\LaravelOdooApi\Odoo\Models;

use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Odoo\Mapping\HasFields;

final class Version
{
    use HasFields;

    public ?int $id = null;

    #[Field('protocol_version')]
    public int $protocolVersion;

    #[Field('server_version')]
    public string $serverVersion;

    #[Field('server_serie')]
    public string $serverSerie;

    #[Field('server_version_info')]
    public array $serverVersionInfo;
}
