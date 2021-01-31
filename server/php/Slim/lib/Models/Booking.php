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

class Booking extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'booking';
    protected $fillable = array(
        'user_id',
        'restaurant_id',
        'reg_date',
        'offer_percentage',
		'from_timeslot'
    );
    public $rules = array(
        'user_id' => 'sometimes|required',
        'restaurant_id' => 'sometimes|required',
        'reg_date' => 'sometimes|required|email',
        'offer_percentage' => 'sometimes|required',
		'from_timeslot' => 'sometimes|required'
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['q'])) {
            $query->where(function ($q1) use ($params) {
                $search = $params['q'];                
            });
        }
		if (!empty($params['user_id'])) {
            $query->where('user_id', '<>' , $params['user_id']);
        }
		if (!empty($params['restaurant_id'])) {
            $query->where('restaurant_id', $params['restaurant_id']);
        }
		$query->whereIn('id', array(0,1));
    }
	public function restaurant()
    {
        return $this->belongsTo('Models\Restaurant', 'restaurant_id', 'id');
    }
	
	public function user()
	{
		return $this->belongsTo('Models\User', 'user_id', 'id');
	}

}
