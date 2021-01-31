<?php
/**
 * Cart
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Menu extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'menus';
    public function user()
    {
        return $this->belongsTo('Models\User', 'user_id', 'id');
    }
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'name',
		'user_id',
		'created_at',
		'updated_at',
		'restaurant_id',
		'name',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'name' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required',
		'open_time' => 'sometimes|required',
		'close_time' => 'sometimes|required',
		'is_active' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
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
    }
}
