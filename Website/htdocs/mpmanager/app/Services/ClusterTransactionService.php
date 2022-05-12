<?php

namespace App\Services;

use App\Models\Transaction\Transaction;
use App\Models\Cluster;
use Illuminate\Database\Eloquent\Collection;

class ClusterTransactionService extends BaseService
{
    public function __construct(private Cluster $cluster, private Transaction $transaction)
    {
        parent::__construct([$cluster, $transaction]);

    }

    public function getById($clusterId, array $range)
    {

        return $this->transaction->newQuery()->whereHas(
            'meter',
            function ($q) use ($clusterId) {
                $q->whereHas(
                    'meterParameter',
                    function ($q) use ($clusterId) {
                        $q->whereHas(
                            'address',
                            function ($q) use ($clusterId) {
                                $q->whereHas(
                                    'city',
                                    function ($q) use ($clusterId) {
                                        $q->where('cluster_id', $clusterId);
                                    }
                                );
                            }
                        );
                    }
                );
            }
        )->whereHasMorph(
            'originalTransaction',
            '*',
            static function ($q) {
                $q->where('status', 1);
            }
        )
            ->whereBetween('created_at', $range)
            ->sum('amount');
    }

}