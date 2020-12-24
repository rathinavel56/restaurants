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
		'created_at',
		'updated_at',
		'center_id',
		'day',
		'type'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'center_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'day' => 'sometimes|required',
		'type' => 'sometimes|required'
    );
	public function slots()
    {
		return $this->hasMany('Models\Slot', 'time_slot_id', 'id')->where('type', 0);
    }
}
