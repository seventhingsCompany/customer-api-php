<?php

declare(strict_types=1);

namespace Seventhings\Models\Enums;

enum SSOProviderName: string
{
    case AzureOpenIdConnect = 'azure-open-id-connect';
    case GoogleOpenIdConnect = 'google-open-id-connect';
    case OneLoginOpenIdConnect = 'one-login-open-id-connect';
}
