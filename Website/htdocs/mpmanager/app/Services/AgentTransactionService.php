<?php

namespace App\Services;

use App\Models\Meter\Meter;
use App\Models\Meter\MeterParameter;
use App\Models\Transaction\AgentTransaction;
use App\Models\Transaction\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AgentTransactionService extends BaseService implements IBaseService
{

    public function __construct(
        private AgentTransaction $agentTransaction,
        private Transaction $transaction
    ) {

        parent::__construct([$agentTransaction, $transaction]);
    }


    /**
     * @return LengthAwarePaginator
     */
    public function list($agentId)
    {
        return Transaction::with(['originalAgent', 'meter.meterParameter.owner'])
            ->whereHasMorph(
                'originalTransaction',
                [AgentTransaction::class],
                static function ($q) use ($agentId) {
                    $q->where('agent_id', $agentId);
                }
            )
            ->latest()->paginate();
    }

    public function listByCustomer($agentId, $customerId): ?LengthAwarePaginator
    {
        $customerMeters = MeterParameter::query()->select('meter_id')->where('owner_id', $customerId)->get();
        if ($customerMeters->count() === 0) {
            return null;
        }
        $meterIds = array();
        foreach ($customerMeters as $key => $item) {
            $meterIds[] = $item->meter_id;
        }

        $customerMeterSerialNumbers = Meter::query()
            ->has('meterParameter')
            ->whereHas(
                'meterParameter',
                static function ($q) use ($meterIds) {
                    $q->whereIn('meter_id', $meterIds);
                }
            )->get('serial_number');

        return Transaction::with(['originalAgent', 'meter.meterParameter.owner'])
            ->whereHasMorph(
                'originalTransaction',
                [AgentTransaction::class],
                static function ($q) use ($agentId) {
                    $q->where('agent_id', $agentId);
                }
            )
            ->whereHas(
                'meter',
                static function ($q) use ($customerMeterSerialNumbers) {
                    $q->whereIn('serial_number', $customerMeterSerialNumbers);
                }
            )
            ->latest()->paginate();
    }


    public function listForWeb(int $agentId): LengthAwarePaginator
    {
        return Transaction::with('meter.meterParameter.owner')
            ->where('type', 'energy')
            ->whereHasMorph(
                'originalTransaction',
                [AgentTransaction::class],
                function ($q) use ($agentId) {
                    $q->where('agent_id', $agentId);
                }
            )
            ->latest()->paginate();


    }

    public function getAll($limit = null, $agentId = null, $forApp = false)
    {
        $query = $this->transaction->newQuery();

        if ($forApp) {
            $query->with(['originalAgent', 'meter.meterParameter.owner']);
        } else {
            $query->with(['meter.meterParameter.owner']);
        }

        $query->whereHasMorph(
            'originalTransaction',
            [AgentTransaction::class],
            static function ($q) use ($agentId) {
                $q->where('agent_id', $agentId);
            }
        );

        if ($limit) {
            return $query->paginate($limit);
        }
        return $query->get();

    }

    public function getById($id)
    {
        // TODO: Implement getById() method.
    }

    public function create($transactionData)
    {
        return $this->agentTransaction->newQuery()->create($transactionData);
    }

    public function update($model, $data)
    {
        // TODO: Implement update() method.
    }

    public function delete($model)
    {
        // TODO: Implement delete() method.
    }


}
