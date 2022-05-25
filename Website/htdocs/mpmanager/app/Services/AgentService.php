<?php

namespace App\Services;

use App\Helpers\PasswordGenerator;
use App\Models\Agent;
use App\Services\CountryService;
use App\Services\PeriodService;
use App\Models\Address\Address;

use App\Models\AgentBalanceHistory;
use App\Models\AgentReceipt;
use App\Models\Country;
use App\Models\Person\Person;
use Complex\Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use phpDocumentor\Reflection\Types\This;

class AgentService extends BaseService implements IBaseService
{

    public function __construct(
        private Agent $agent,
        private AgentReceipt $agentReceipt,
        private AgentBalanceHistory $agentBalanceHistory,
        private PeriodService $periodService
    ) {

        parent::__construct([$agent]);
    }

    /**
     * @param string $email
     * @return int|string
     */
    public function resetPassword(string $email)
    {
        try {
            $newPassword = PasswordGenerator::generatePassword();
        } catch (Exception $exception) {
            $newPassword = time();
        }
        try {
            $agent = $this->agent->newQuery()->where('email', $email)->firstOrFail();
        } catch (ModelNotFoundException $x) {
            $message = 'Invalid email.';
            return $message;
        }

        $agent->password = $newPassword;
        $agent->update();
        $agent->fresh();
        return $newPassword;
    }

    public function updateDevice($agent, $deviceId): void
    {
        $agent->device_id = $deviceId;
        $agent->update();
        $agent->fresh();
    }

    public function setFirebaseToken($agent, $firebaseToken)
    {
        $agent->fire_base_token = $firebaseToken;
        $agent->update();

        return $agent->fresh();
    }

    public function getAgentBalance($agent)
    {
        return $agent->balance;
    }

    public function searchAgent($searchTerm, $paginate)
    {
        if ($paginate === 1) {
            return $this->agent->newQuery()->with('miniGrid')->WhereHas(
                'miniGrid',
                function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', '%' . $searchTerm . '%');
                }
            )->orWhere('name', 'LIKE', '%' . $searchTerm . '%')
                ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')->paginate(15);
        }

        return $this->agent->newQuery()->with('miniGrid')->WhereHas(
            'miniGrid',
            function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%');
            }
        )->orWhere('name', 'LIKE', '%' . $searchTerm . '%')
            ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')->get();
    }

    public function getByAuthenticatedUser()
    {
        return $this->agent->newQuery()->find(auth('agent_api')->user()->id);
    }

    public function getById($id)
    {
        return $this->agent->newQuery()
            ->with(['person', 'person.addresses', 'miniGrid', 'commission'])
            ->where('id', $id)->firstOrFail();
    }

    public function delete($agent)
    {
        return $agent->delete();
    }

    public function getAll($limit = null)
    {
        if ($limit) {
            return $this->agent->newQuery()
                ->with(['person', 'person.addresses', 'miniGrid', 'commission'])
                ->paginate($limit);
        }

        return $this->agent->newQuery()
            ->with(['person.addresses', 'miniGrid'])
            ->get();
    }

    public function create(
        $agentData,
        $addressData = null,
        $personData = null,
        $country = null,
        $addressService = null,
        $countryService = null,
        $personService = null,
        $personAddressService = null,
    ) {
        $person = $personService->create($personData);

        if ($country !== null) {
            $person = $personService->addCitizenship($person, $country);
        }

        $agentData['person_id'] = $person->id;
        $agentData['name'] = $person->name;
        $address = $addressService->make($addressData);
        $personAddressService->setAssigner($person);
        $personAddressService->setAssigned($address);
        $personAddressService->assign();
        $addressService->save($address);

        return $this->agent->newQuery()->create($agentData);
    }

    /**
     * @param $agent
     * @param $data
     * @return Model|Builder
     */
    public function update($agent, $agentData, $personService = null)
    {

        $person = $personService->getById($agentData['personId']);
        $personData = [
            'name' => $agentData['name'],
            'surname' => $agentData['surname'],
            'sex' => $agentData['gender'],
            'birth_date' => $agentData['birthday']
        ];
        $person = $personService->update($person, $personData);
        $address = $person->addresses()->where('is_primary', 1)->first();
        $address->phone = $agentData['phone'];
        $address->update();
        $agent->name = $agentData['name'];
        $agent->agent_commission_id = $agentData['commissionTypeId'];
        $agent->update();

        return $this->agent->with(['person', 'person.addresses', 'miniGrid', 'commission'])
            ->where('id', $agent->id)->first();
    }

}
