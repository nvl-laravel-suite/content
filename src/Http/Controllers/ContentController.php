<?php

declare(strict_types=1);

namespace Nvl\Content\Http\Controllers;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Nvl\Content\Exceptions\ContentException;
use Nvl\Content\Exceptions\InvalidContentException;
use Nvl\Support\Http\PackageExceptionPayload;

/**
 * Presentation adapter for transport-neutral failures on opt-in Content routes.
 */
abstract class ContentController extends Controller
{
    public function __construct(private readonly PackageExceptionPayload $payload) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    final protected function content(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $exception) {
            $this->throwResponse(InvalidContentException::fromInvalidArgument($exception));
        } catch (ContentException $exception) {
            $this->throwResponse($exception);
        }
    }

    private function throwResponse(ContentException $exception): never
    {
        $payload = $this->payload->for($exception);

        throw new HttpResponseException(new JsonResponse([
            'message' => $payload['message'],
            'error' => [
                'code' => $payload['code'],
                'context' => $payload['context'],
            ],
        ], $exception->suggestedStatus(), $this->payload->headers($exception)));
    }
}
