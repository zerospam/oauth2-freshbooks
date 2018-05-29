<?php

namespace ZEROSPAM\OAuth2\Client\Provider\Test;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use PHPUnit\Framework\TestCase;
use ZEROSPAM\OAuth2\Client\Provider\FreshBook;
use Mockery as m;
use ZEROSPAM\OAuth2\Client\Provider\FreshBookOwner;

/**
 * Created by PhpStorm.
 * User: aaflalo
 * Date: 18-05-29
 * Time: 15:16
 */
class FreshBookTest extends TestCase
{
    use QueryBuilderTrait;
    /**
     * @var FreshBook
     */
    protected $provider;

    private function generateRandomString($length = 10)
    {
        $characters       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString     = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    protected function setUp()
    {
        $this->provider = new FreshBook(
            [
                'clientId'     => 'mock_client_id',
                'clientSecret' => 'mock_client_secret',
                'redirectUri'  => 'redirect_url',
            ]
        );
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);
        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testScopes()
    {
        $scopeSeparator = ',';
        $options        = ['scope' => [$this->generateRandomString(), $this->generateRandomString()]];
        $query          = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url            = $this->provider->getAuthorizationUrl($options);
        $encodedScope   = $this->buildQueryString($query);
        $this->assertContains($encodedScope, $url);
    }

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        $this->assertEquals('/service/auth/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];
        $url    = $this->provider->getBaseAccessTokenUrl($params);
        $uri    = parse_url($url);
        $this->assertEquals('/auth/oauth/token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $response = m::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')
                 ->andReturn('{"access_token": "mock_access_token","scopes": "account","expires_in": 3600,"refresh_token": "mock_refresh_token","token_type": "bearer"}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertLessThanOrEqual(time() + 3600, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
        $this->assertEquals('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testUserData()
    {
        $id        = mt_rand();
        $firstName = $this->generateRandomString();
        $lastName  = $this->generateRandomString();
        $email     = $this->generateRandomString() . '@example.com';
        $json
                   = <<<JSON
{
  "response": {
    "id": $id,
    "profile": {
      "setup_complete": true,
      "first_name": "$firstName",
      "last_name": "$lastName"
    },
    "first_name": "$firstName",
    "last_name": "$lastName",
    "email": "$email"
  }
}
JSON;

        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')
                     ->andReturn('{"access_token": "mock_access_token","scopes": "account","expires_in": 3600,"refresh_token": "mock_refresh_token","token_type": "bearer"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($json);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
               ->times(2)
               ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /**
         * @var $user FreshBookOwner
         */
        $user = $this->provider->getResourceOwner($token);
        $this->assertEquals($id, $user->getId());
        $this->assertEquals($firstName, $user->getFirstName());
        $this->assertEquals($lastName, $user->getLastName());
        $this->assertEquals($email, $user->getEmail());
    }

    /**
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     * @expectedExceptionMessage The requested resource was not found.
     */
    public function testHeaderMissing()
    {
        $error
                      = '{
    "message": "The requested resource was not found.",
    "error_type": "not_found"
}';
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')
                     ->andReturn('{"access_token": "mock_access_token","scopes": "account","expires_in": 3600,"refresh_token": "mock_refresh_token","token_type": "bearer"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($error);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(401);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
               ->times(2)
               ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->provider->getResourceOwner($token);

    }

    /**
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     * @expectedExceptionMessage The provided authorization grant is invalid, expired, revoked, does not match the redirection URI used in the authorization request, or was issued to another client.
     */
    public function testInvalidGrant()
    {
        $error
                      = '{
    "error": "invalid_grant",
    "error_description": "The provided authorization grant is invalid, expired, revoked, does not match the redirection URI used in the authorization request, or was issued to another client."
}';
        $postResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')
                     ->andReturn('{"access_token": "mock_access_token","scopes": "account","expires_in": 3600,"refresh_token": "mock_refresh_token","token_type": "bearer"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse = m::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn($error);
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(401);
        $client = m::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
               ->times(2)
               ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->provider->getResourceOwner($token);

    }
}
