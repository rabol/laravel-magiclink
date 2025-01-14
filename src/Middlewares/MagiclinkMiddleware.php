<?php

namespace MagicLink\Middlewares;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use MagicLink\MagicLink;
use MagicLink\Responses\Response;

class MagiclinkMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->route('token');

        $magicLink = MagicLink::getValidMagicLinkByToken($token);

        if (! $magicLink) {
            return $this->badResponse();
        }

        if ($magicLink->protectedWithAcessCode()) {
            if ($magicLink->checkAccessCode($request->get('access-code'))) {
                // access code is valid
                return redirect($request->url())->withCookie(
                    cookie(
                        'magic-link-access-code',
                        encrypt($request->get('access-code')),
                        0,
                        '/'
                    )
                );
            }

            try {
                $accessCode = decrypt($request->cookie('magic-link-access-code'));

                // Validate access_code
                if ($magicLink->checkAccessCode($accessCode)) {
                    $magicLink->visited();

                    return $next($request);
                }
            } catch (DecryptException $e) {
                // empty value in cookie
            }

            return response(view('magiclink::ask-for-access-code-form'), 403);
        }

        $magicLink->visited();

        return $next($request);
    }

    // private function isAccessCodeValid(string $token, ?string $accessCode): bool
    // {
    //     if ($accessCode === null) {
    //         return false;
    //     }

    //     $magicLink = MagicLink::getValidMagicLinkByToken($token);

    //     return Hash::check($accessCode, $magicLink->access_code);
    // }

    protected function badResponse()
    {
        $responseClass = config('magiclink.invalid_response.class', Response::class);

        $response = new $responseClass;

        return $response(config('magiclink.invalid_response.options', []));
    }
}
