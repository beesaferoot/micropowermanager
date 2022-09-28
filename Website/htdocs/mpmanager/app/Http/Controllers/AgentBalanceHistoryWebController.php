<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResource;
use App\Models\Agent;
use App\Services\AgentBalanceHistoryService;
use Illuminate\Http\Request;

class AgentBalanceHistoryWebController extends Controller
{
    public function __construct(private AgentBalanceHistoryService $agentBalanceHistoryService)
    {
    }


    public function index($agentId, Request $request)
    {
        $limit = $request->input('limit');

        return  ApiResource::make($this->agentBalanceHistoryService->getAll($limit,$agentId));
    }
}