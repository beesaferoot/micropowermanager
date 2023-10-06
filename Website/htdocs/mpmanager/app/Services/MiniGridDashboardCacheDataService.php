<?php

namespace App\Services;

use App\Models\City;
use App\Models\ConnectionGroup;
use App\Models\Revenue;
use App\Models\Target;
use DateInterval;
use DatePeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inensus\Ticket\Models\Ticket;
use Inensus\Ticket\Models\TicketCategory;
use Nette\Utils\DateTime;
use function PHPUnit\Framework\isEmpty;

class MiniGridDashboardCacheDataService extends AbstractDashboardCacheDataService
{
    private const CACHE_KEY_MINI_GRIDS_DATA = 'MiniGridsData';

    public function __construct(
        private MeterService $meterService,
        private MiniGridRevenueService $miniGridRevenueService,
        private MiniGridService $miniGridService,
        private Target $target,
        private ConnectionGroup $connectionGroup,
        private ConnectionGroupService $connectionGroupService,
        private Revenue $revenue,
        private City $city,
        private ConnectionTypeService $connectionTypeService,
        private PeriodService $periodService,
        private Ticket $ticket,
        private TicketCategory $label,
    ) {
        parent::__construct(self::CACHE_KEY_MINI_GRIDS_DATA);
    }

    public function setData($dateRange = [])
    {
        if (empty($dateRange)) {
            $startDate = date('Y-m-d H:i:s', strtotime('today - 2 year'));
            $endDate = date('Y-m-d H:i:s', strtotime('today'));
            $dateRange[0] = $startDate;
            $dateRange[1] = $endDate;
        } else {
            list($startDate, $endDate) = $dateRange;
        }


        $miniGrids = $this->miniGridService->getAll();
        $connections = $this->connectionGroupService->getAll();

        //get list of tariffs
        $connectionsTypes = $this->connectionTypeService->getAll();
        $connectionNames = $connectionsTypes->pluck('name')->toArray();

        foreach ($miniGrids as $index => $miniGrid) {
            $miniGridId = $miniGrid->id;
            $miniGrids[$index]->soldEnergy = $this->miniGridRevenueService->getSoldEnergyById(
                $miniGridId,
                $startDate,
                $endDate,
                $this->meterService
            );
            $miniGrids[$index]->transactions = $this->miniGridRevenueService->getById(
                $miniGridId,
                $startDate,
                $endDate,
                $this->meterService
            );
            Log::info('asdaasd',['asd' =>$miniGrids[$index]->transactions]);
            $targets = $this->target->targetForMiniGrid($miniGridId, $endDate)->first();
            $formattedTarget = [];
            foreach ($connections as $connection) {
                $formattedTarget[$connection->name] = [
                    'new_connections' => '-',
                    'revenue' => '-',
                    'connected_power' => '-',
                    'energy_per_month' => '-',
                    'average_revenue_per_month' => '-',
                ];
            }
            if ($targets !== null) {
                foreach ($targets->subTargets as $subTarget) {
                    $formattedTarget[$subTarget->connectionType->name] = [
                        'new_connections' => $subTarget->new_connections,
                        'revenue' => $subTarget->revenue,
                        'connected_power' => $subTarget->connected_power,
                        'energy_per_month' => $subTarget->energy_per_month,
                        'average_revenue_per_month' => $subTarget->average_revenue_per_month,
                    ];
                }
                unset($targets->subTargets);
            }
            //get all types of connections
            $connectionGroups = $this->connectionGroup->select('id', 'name')->get();
            $connections = [];
            $revenues = [];
            $totalConnections = [];
            foreach ($connectionGroups as $connectionGroup) {

                $revenue = $this->revenue->connectionGroupForMiniGridBasedPeriod(
                    $miniGridId,
                    $connectionGroup->id,
                    $startDate,
                    $endDate
                );
                $totalConnectionsData = $this->revenue->registeredMetersForMiniGridByConnectionGroupTill(
                    $miniGridId,
                    $connectionGroup->id,
                    $endDate
                );
                $totalConnections[$connectionGroup->name] = $totalConnectionsData[0]["registered_connections"];
                $revenues[$connectionGroup->name] = $revenue[0]['total'] ?? 0;
                $connectionsData = $this->revenue->miniGridMetersByConnectionGroup(
                    $miniGridId,
                    $connectionGroup->id,
                    $startDate,
                    $endDate
                );
                $connections[$connectionGroup->name] = $connectionsData[0]['registered_connections'];
            }


            $cities = $this->city::where('mini_grid_id', $miniGridId)->get();
            $cityIds = implode(',', $cities->pluck('id')->toArray());
            $initialData = array_fill_keys($connectionNames, ['revenue' => 0]);

            $response = $this->periodService->generatePeriodicList(
                $startDate,
                $endDate,
                'weekly',
                $initialData
            );
            foreach ($connectionsTypes as $connectionType) {
                $tariffRevenue = $this->revenue->weeklyConnectionBalances(
                    $cityIds,
                    $connectionType->id,
                    $startDate,
                    $endDate
                );

                foreach ($tariffRevenue as $revenue) {
                    $totalRevenue = (int)$revenue['total'];
                    $date = $this->reformatPeriod($revenue['result_date']);
                    $response[$date][$connectionType->name] = [
                        'revenue' => $totalRevenue,
                    ];
                }
            }


            $begin = date_create('2018-08-01');
            $end = date_create();
            $end->add(new DateInterval('P1D')); //
            $i = new DateInterval('P1W');
            $period = new DatePeriod($begin, $i, $end);

            $openedTicketsWithCategories = $this->ticket->ticketsOpenedWithCategories($miniGridId);
            $closedTicketsWithCategories = $this->ticket->ticketsClosedWithCategories($miniGridId);
            $ticketCategories = $this->label->all();
            $result = [];
            $result['categories'] = $ticketCategories->toArray();
            foreach ($period as $d) {
                $day = $d->format('o-W');
                foreach ($ticketCategories as $tC) {
                    $result[$day][$tC->label_name]['opened'] = 0;
                    $result[$day][$tC->label_name]['closed'] = 0;
                }
            }

            foreach ($closedTicketsWithCategories as $closedTicketsWithCategory) {
                $date = $this->reformatPeriod($closedTicketsWithCategory["period"]);
                $result[$date][$closedTicketsWithCategory["label_name"]]["closed"]
                    = $closedTicketsWithCategory["closed_tickets"];
            }

            foreach ($openedTicketsWithCategories as $openedTicketsWithCategory) {
                $date = $this->reformatPeriod($openedTicketsWithCategory["period"]);
                $result[$date][$openedTicketsWithCategory["label_name"]]["opened"]
                    = $openedTicketsWithCategory["new_tickets"];
            }
            $miniGrids[$index]->period = $response;
            $miniGrids[$index]->tickets = $result;
            $miniGrids[$index]->revenueList = [
                'totalConnections' => $totalConnections,
                'revenue' => $revenues,
                'newConnections' => $connections,
                'target' => ['targets' => $formattedTarget],
            ];
        }
        Cache::put(self::cacheKeyGenerator(), $miniGrids, DateTime::from('+ 1 day'));
    }

}