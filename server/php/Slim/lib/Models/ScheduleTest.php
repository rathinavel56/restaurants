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
        'qr_code'
    );
    public $rules = array(
        'user_id' => 'sometimes|required',
        'center_id' => 'sometimes|required',
        'reg_date' => 'sometimes|required|email',
        'qr_code' => 'sometimes|required'
    );
	public function center()
    {
        return $this->belongsTo('Models\Center', 'center_id', 'id')->with('island');
    }
}
