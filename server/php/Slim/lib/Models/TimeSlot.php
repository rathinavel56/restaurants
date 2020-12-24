<?php
/**
 * Advertisement
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class TimeSlot extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'time_slots';
    public $hidden = array(
        'created_at',
        'updated_at'
    );
    protected $fillable = array(
        'id',
		'center_id',
		'created_at',
		'updated_at',
		'day',
		'from_timeslot',
		'to_timeslot'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'center_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'day' => 'sometimes|required',
		'from_timeslot' => 'sometimes|required',
		'to_timeslot' => 'sometimes|required'
    );
}
