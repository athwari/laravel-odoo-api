<?php

namespace Athwari\LaravelOdooApi\Commands;

use Athwari\LaravelOdooApi\Exceptions\OdooException;
use Athwari\LaravelOdooApi\Odoo;
use Illuminate\Console\Command;
use Throwable;

class FieldsCommand extends Command
{
    protected $signature = 'odoo:fields {model : The Odoo model to inspect (e.g., res.partner)} {--json : Output raw JSON format}';

    protected $description = 'List all fields and their definitions for an Odoo model.';

    public function handle(Odoo $odoo): int
    {
        $model = $this->argument('model');

        try {
            $fields = $odoo->listModelFields($model);

            if ($this->option('json')) {
                $this->line(json_encode($fields, JSON_PRETTY_PRINT));

                return self::SUCCESS;
            }

            $rows = [];
            foreach ((array) $fields as $fieldName => $definition) {
                // Determine attributes carefully since Odoo versions and custom fields may vary.
                $defArray = (array) $definition;
                $type = $defArray['type'] ?? 'unknown';
                $required = isset($defArray['required']) && $defArray['required'] ? 'yes' : 'no';

                $rows[] = [
                    $fieldName,
                    $type,
                    $required,
                ];
            }

            // Sort fields alphabetically for easier reading
            usort($rows, fn ($a, $b) => strcmp($a[0], $b[0]));

            $this->table(['Field', 'Type', 'Required'], $rows);

            return self::SUCCESS;

        } catch (OdooException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('An unexpected error occurred: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
