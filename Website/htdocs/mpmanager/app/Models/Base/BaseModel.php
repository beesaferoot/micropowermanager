<?php

/**
 * Created by PhpStorm.
 * User: kemal
 * Date: 29.05.18
 * Time: 10:57.
 */

namespace App\Models\Base;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model for models which are implemented on tenant databases.
 *
 * This applies to all models in MPM which implement the business
 * logic and define interaction between entities.
 * "Most" models will extend this base model.
 */
class BaseModel extends Model
{
    protected $guarded = ['id'];
    public static $rules = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection('shard');
    }
}
