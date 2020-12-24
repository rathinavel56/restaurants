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

class Island extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'islands';
    protected $fillable = array(
        'id',
        'name',
        'reg_date',
        'qr_code'
    );
    public $rules = array(
        'user_id' => 'sometimes|required',
        'center_id' => 'sometimes|required',
        'reg_date' => 'sometimes|required|email',
        'qr_code' => 'sometimes|required'
    );
	public function centers()
    {
		return $this->hasMany('Models\Center', 'island_id', 'id');
    }
}
