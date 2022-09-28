<?php

namespace Inensus\Ticket\Http\Controllers;

use App\Services\PersonService;
use App\Services\PersonTicketService;
use App\Services\UserTicketService;
use Illuminate\Http\Request;
use Inensus\Ticket\Exceptions\TicketOwnerNotFoundException;
use Inensus\Ticket\Http\Requests\UserTicketCreateRequest;
use Inensus\Ticket\Http\Resources\TicketResource;
use Inensus\Ticket\Services\TicketCategoryService;
use Inensus\Ticket\Services\TicketOutSourceService;
use Inensus\Ticket\Services\TicketService;
use Inensus\Ticket\Services\TicketUserService;
use Ramsey\Uuid\Uuid;

class TicketCustomerController extends Controller
{
    public function __construct(
        private TicketService $ticketService,
        private UserTicketService $userTicketService,
        private PersonTicketService $personTicketService,
        private TicketCategoryService $ticketCategoryService,
        private PersonService $personService,
        private TicketOutSourceService $ticketOutSourceService,

    ) {

    }

    public function store(UserTicketCreateRequest $request): TicketResource
    {

        $ticketData = $request->getMappedArray();

        $owner = $this->personService->getById($request->getOwnerId());

        if (!$owner) {
            throw new TicketOwnerNotFoundException('Ticket owner with following id not found ' . $request->getOwnerId());
        }

        $user = auth('api')->user();
        $ticket = $this->ticketService->make($ticketData);
        $this->userTicketService->setAssigned($ticket);
        $this->userTicketService->setAssigner($user);
        $this->userTicketService->assign();
        $this->personTicketService->setAssigned($ticket);
        $this->personTicketService->setAssigner($owner);
        $this->personTicketService->assign();
        $this->ticketService->save($ticket);
        //get category to check outsourcing
        $categoryData = $this->ticketCategoryService->getById($request->getLabel());

        if ($categoryData->out_source) {
            $ticketOutsourceData = [
                'ticket_id' => $ticket->id,
                'amount' => (int)$request->get('outsourcing')
            ];
            $this->ticketOutSourceService->create($ticketOutsourceData);
        }

        return TicketResource::make($ticket);
    }

    public function index($customerId, Request $request)
    {

        $limit = 5;
        $agentId = null;
        $status = null;

        return TicketResource::make($this->ticketService->getAll($limit, $status, $agentId, $customerId));

    }
}