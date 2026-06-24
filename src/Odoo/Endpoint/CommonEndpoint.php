<?php

namespace Athwari\LaravelOdooApi\Odoo\Endpoint;

use Athwari\LaravelOdooApi\Exceptions\AuthenticationException;
use Athwari\LaravelOdooApi\Odoo\Models\Version;

class CommonEndpoint extends Endpoint
{
    protected string $service = 'common';

    /**
     * Authenticate with Odoo and return the user ID.
     *
     * If the config has a fixed user ID set, the authenticate RPC call
     * is skipped entirely and the fixed ID is returned directly.
     *
     * @throws AuthenticationException
     */
    public function authenticate(): int
    {
        if ($this->getConfig()->hasFixedUserId()) {
            return $this->getConfig()->getFixedUserId();
        }

        $client = $this->getClient();
        $uid = $client->authenticate(
            $this->getConfig()->getDatabase(),
            $this->getConfig()->getUsername(),
            $this->getConfig()->getCredential(),
            ['empty' => 'false'],
        );

        if (is_int($uid) && $uid > 0) {
            return $uid;
        }

        $authMethod = $this->getConfig()->isUsingApiKey() ? 'API key' : 'password';

        throw new AuthenticationException(
            "Odoo authentication failed using {$authMethod}. "
            .'Please check your database, username, and credentials. '
            .'Ensure the Odoo server is reachable and the user account is active.',
        );
    }

    public function version(): Version
    {
        return Version::hydrate(
            (object) $this->getClient()->version()
        );
    }
}
