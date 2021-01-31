<?php
/**
 * Advertisement
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Slot extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'slots';
    public $hidden = array(
        'created_at',
        'updated_at'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'time_slot_id',
		'type',
		'from_timeslot',
		'slot_count'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'time_slot_id' => 'sometimes|required',
		'type' => 'sometimes|required',
		'from_timeslot' => 'sometimes|required',
		'slot_count' => 'sometimes|required'
    );
}
