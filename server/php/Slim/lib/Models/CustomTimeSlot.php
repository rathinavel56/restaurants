<?php
/**
 * Advertisement
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class CustomTimeSlot extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'custom_time_slots';
    public $hidden = array(
        'created_at',
        'updated_at'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'restaurant_id',
		'date_detail',
		'type'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'date_detail' => 'sometimes|required',
		'type' => 'sometimes|required'
    );
	public function slots()
    {
		return $this->hasMany('Models\Slot', 'time_slot_id', 'id')->where('type', 1);
    }
}
