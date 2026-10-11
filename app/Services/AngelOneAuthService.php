<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AngelOneAuthService
{
    private const LOGIN_URL = 'https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword';

    /**
     * @param array{
     *     clientcode: string,
     *     password: string,
     *     totp: string,
     *     state: string,
     *     api_key: string,
     *     client_local_ip: string,
     *     client_public_ip: string,
     *     mac_address: string
     * } $credentials
     * @return array{jwt_token: string, refresh_token: string, feed_token: string}
     */
    public function login(array $credentials): array
    {
        $response = Http::asJson()
            ->acceptJson()
            ->withHeaders([
                'X-UserType' => 'USER',
                'X-SourceID' => 'WEB',
                'X-ClientLocalIP' => $credentials['client_local_ip'],
                'X-ClientPublicIP' => $credentials['client_public_ip'],
                'X-MACAddress' => $credentials['mac_address'],
                'X-PrivateKey' => $credentials['api_key'],
            ])
            ->connectTimeout(5)
            ->timeout(15)
            ->post(self::LOGIN_URL, [
                'clientcode' => $credentials['clientcode'],
                'password' => $credentials['password'],
                'totp' => $credentials['totp'],
                'state' => $credentials['state'],
            ]);

        $payload = $response->json();
        $data = is_array($payload) && is_array($payload['data'] ?? null)
            ? $payload['data']
            : [];

        if (
            !$response->successful()
            || ($payload['status'] ?? false) !== true
            || !is_string($data['jwtToken'] ?? null)
            || $data['jwtToken'] === ''
        ) {
            throw new RuntimeException(
                'Angel One did not authenticate this request. Check the API key, client code, PIN, TOTP, and network details.'
            );
        }

        return [
            'jwt_token' => $data['jwtToken'],
            'refresh_token' => is_string($data['refreshToken'] ?? null) ? $data['refreshToken'] : '',
            'feed_token' => is_string($data['feedToken'] ?? null) ? $data['feedToken'] : '',
        ];
    }
}
