<?php
/**
 * Contact
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Model
 */
namespace Models;

class ScheduleTest extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'schedule_test';
    protected $fillable = array(
        'user_id',
        'center_id',
        'reg_date',
        'qr_code',
		'from_timeslot',
		'test_type_id'
    );
    public $rules = array(
        'user_id' => 'sometimes|required',
        'center_id' => 'sometimes|required',
        'reg_date' => 'sometimes|required|email',
        'qr_code' => 'sometimes|required',
		'from_timeslot' => 'sometimes|required',
		'test_type_id' => 'sometimes|required'
    );
	public function center()
    {
        return $this->belongsTo('Models\User', 'center_id', 'id')->with('island');
    }
}
