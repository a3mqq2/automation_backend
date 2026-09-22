<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Automation\Engine\WebhookGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaWebhookController extends Controller
{
    private const PLAIN_TEXT = ['Content-Type' => 'text/plain'];

    public function __construct(private readonly WebhookGateway $gateway)
    {
    }

    public function verify(Request $request): Response
    {
        return response($this->gateway->challenge($request->query()), 200, self::PLAIN_TEXT);
    }

    public function receive(Request $request): Response
    {
        $this->gateway->accept($request->json()->all());

        return response('EVENT_RECEIVED', 200, self::PLAIN_TEXT);
    }
}
