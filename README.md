# Freshbookss Provider for OAuth 2.0 Client

[![Latest Version](https://img.shields.io/github/release/zerospam/oauth2-freshbooks.svg?style=flat-square)](https://github.com/zerospam/oauth2-freshbooks/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://travis-ci.com/zerospam/oauth2-freshbooks.svg?branch=master)](https://travis-ci.com/zerospam/oauth2-freshbooks)
[![Total Downloads](https://img.shields.io/packagist/dt/zerospam/oauth2-freshbooks.svg?style=flat-square)](https://packagist.org/packages/zerospam/oauth2-freshbooks)

This package provides Freshbooks OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

To install, use composer:

```
composer require zerospam/oauth2-freshbooks
```

## Usage

Usage is the same as The League's OAuth client, using `\ZEROSPAM\OAuth2\Client\Provider\Freshbooks` as the provider.

### Authorization Code Flow

```php
$provider = new ZEROSPAM\OAuth2\Client\Provider\Freshbooks([
    'clientId'          => '{freshbooks-client-id}',
    'clientSecret'      => '{freshbooks-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url'
]);

if (!isset($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: '.$authUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the user's details
        $user = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $user->getId());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Oh dear...');
    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();
}
```

### Refreshing a Token

```php
$provider = new ZEROSPAM\OAuth2\Client\Provider\Freshbooks([
    'clientId'          => '{freshbooks-client-id}',
    'clientSecret'      => '{freshbooks-client-secret}',
    'redirectUri'       => 'https://example.com/callback-url'
]);

$grant = new \League\OAuth2\Client\Grant\RefreshToken();
$token = $provider->getAccessToken($grant, ['refresh_token' => $token->refreshToken]);
```

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/zerospam/oauth2-freshbooks/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Antoine Aflalo](https://github.com/Belphemur)
- [All Contributors](https://github.com/zerospam/oauth2-freshbooks/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/zerospam/oauth2-freshbooks/blob/master/LICENSE) for more information.
