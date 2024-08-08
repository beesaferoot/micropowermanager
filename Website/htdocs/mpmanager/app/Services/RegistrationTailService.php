<?php

namespace App\Services;

use App\Models\RegistrationTail;
use App\Services\Interfaces\IBaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @implements IBaseService<RegistrationTail>
 */
class RegistrationTailService implements IBaseService
{
    public function __construct(
        private RegistrationTail $registrationTail
    ) {
    }

    public function getById(int $id): RegistrationTail
    {
        return $this->registrationTail->newQuery()->find($id);
    }

    public function create(array $registrationTailData): RegistrationTail
    {
        return $this->registrationTail->newQuery()->create($registrationTailData);
    }

    public function update($registrationTail, array $registrationTailData): RegistrationTail
    {
        if (array_key_exists('tail', $registrationTailData)) {
            $registrationTail->update($registrationTailData);
        } else {
            $registrationTail->update(['tail' => $registrationTailData]);
        }

        $registrationTail->fresh();

        return $registrationTail;
    }

    public function delete($model): ?bool
    {
        throw new \Exception('Method delete() not yet implemented.');
    }

    public function getAll(?int $limit = null): Collection|LengthAwarePaginator
    {
        return $this->registrationTail->newQuery()->get();
    }

    public function getFirst($limit = null)
    {
        return $this->registrationTail->newQuery()->first();
    }

    /**
     * @param mixed $tail
     * @param mixed $mpmPlugin
     * @param mixed $registrationTail
     *
     * @return mixed
     */
    public function resetTail(mixed $tail, mixed $mpmPlugin, mixed $registrationTail): mixed
    {
        array_push($tail, [
            'tag' => $mpmPlugin->tail_tag,
            'component' => isset($mpmPlugin->tail_tag) ? str_replace(
                ' ',
                '-',
                $mpmPlugin->tail_tag
            ) : null,
            'adjusted' => !isset($mpmPlugin->tail_tag),
        ]);
        $this->update(
            $registrationTail,
            ['tail' => json_encode($tail)]
        );

        return $tail;
    }
}
