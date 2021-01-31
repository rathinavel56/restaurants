<?php
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class OperatingHour extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'operating_hours';
	public $hidden = array(
        'created_at',
        'updated_at'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'type',
		'restaurant_id',
		'day_id',
		'from',
		'to'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required'
    );
	public function day()
	{
		return $this->belongsTo('Models\Day', 'day_id', 'id');
	}
}
