<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResource;
use App\Services\AgentService;
use Illuminate\Http\Request;
use Inensus\Ticket\Services\TicketService;

class AgentCustomerTicketController extends Controller
{
    public function __construct(private AgentService $agentService, private TicketService $ticketService)
    {
    }

    public function show($customerId, Request $request): ApiResource
    {
        $agent = $this->agentService->getByAuthenticatedUser();
        $limit = $request->input('limit');

        return ApiResource::make($this->ticketService->getAll($limit, $agent->id, $customerId));
    }
}
