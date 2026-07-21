<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureTelescopeIsLocal;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureTelescopeIsLocalTest extends TestCase
{
    public static function loopbackAddresses(): array
    {
        return [
            'IPv4' => ['127.0.0.1'],
            'IPv6' => ['::1'],
        ];
    }

    #[DataProvider('loopbackAddresses')]
    public function test_it_allows_access_from_the_server_computer(string $address): void
    {
        $request = Request::create('/telescope', server: ['REMOTE_ADDR' => $address]);

        $response = (new EnsureTelescopeIsLocal)->handle(
            $request,
            fn (): Response => new Response('Telescope'),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_it_rejects_access_from_other_lan_devices(): void
    {
        $request = Request::create('/telescope', server: ['REMOTE_ADDR' => '192.168.1.50']);

        try {
            (new EnsureTelescopeIsLocal)->handle(
                $request,
                fn (): Response => new Response('Telescope'),
            );

            $this->fail('A LAN device was allowed to open Telescope.');
        } catch (HttpException $exception) {
            $this->assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        }
    }
}
