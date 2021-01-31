<?php
/**
 * Size
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Restaurant extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'restaurants';
	public $hidden = array(
        'created_at',
        'updated_at'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'user_id',
		'city_id',
		'brand_id',
		'title',
		'address',
		'latitude',
		'longitude',
		'description',
		'disclaimer',
		'reservations',
		'vouchers',
		'star_rating',
		'rupee_rating',
		'max_person',
		'is_admin_deactived',
		'is_deactived'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'name' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required'
    );
    public $qSearchFields = array(
        'name'
    );
	public function scopeFilter($query, $params = array())
    {
        global $authUser;
        parent::scopeFilter($query, $params);
        if (!empty($params['search']) && $params['search'] != 'undefined') {
			$search = $params['search'];
			$query->where('title', 'like', "%$search%");
        }
		if (!empty($params['user_id'])) {
            $query->Where('user_id', $params['user_id']);
        }
		if (!empty($params['user_id'])) {
            $query->Where('user_id', $params['user_id']);
        }
		$query->Where('is_deactived', 0);
		if (empty($params['is_admin_deactived'])) {
            $query->Where('is_admin_deactived', 0);
        }
		if (!empty($params['restaurants'])) {
            $query->whereIn('id', $params['restaurants']);
        }
		if (!empty($params['brand_id'])) {
            $query->Where('brand_id', $params['brand_id']);
        }
		if (!empty($params['city_id'])) {
            $query->Where('city_id', $params['city_id']);
        }
		if (!empty($params['most_reserved'])) {
			// $query->orderBy('reservations', $params['most_reserved']);
		} else if (!empty($params['recommended'])) {
			$query->orderBy('star_rating', $params['most_reserved']);
		} else if (!empty($params['distance'])) {
			$query->orderBy('star_rating', $params['most_reserved']);
		} else if (!empty($queryParams['star_rating'])) {
			$query->orderBy('star_rating', $params['most_reserved']);
		} else if (!empty($queryParams['price'])) {
			$query->orderBy('price', $params['asc']);
		}
    }
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'Restaurant');
    }
	public function attachments()
    {
        return $this->hasMany('Models\Attachment', 'foreign_id', 'id')->where('class', 'Restaurant');
    }
	public function slots()
    {
		return $this->hasMany('Models\TimeSlot', 'restaurant_id', 'id')->with('slots')->where('type', 0);
    }
	public function menus()
    {
		return $this->hasMany('Models\Menu', 'restaurant_id', 'id')->where('is_active', 1);
    }
	public function special_conditions()
    {
		return $this->hasMany('Models\SpecialCondition', 'restaurant_id', 'id');
    }
	public function facilities_services()
    {
		return $this->hasMany('Models\FacilitiesService', 'restaurant_id', 'id');
    }
	public function atmospheres()
    {
		return $this->hasMany('Models\Atmosphere', 'restaurant_id', 'id');
    }
	public function languages()
    {
		return $this->hasMany('Models\RestaurantLanguage', 'restaurant_id', 'id')->with('language');
    }
	public function operating_hours()
    {
		return $this->hasMany('Models\OperatingHour', 'restaurant_id', 'id')->with('day');
    }
	public function about()
	{
		return $this->belongsTo('Models\RestaurantAboutUs', 'id', 'restaurant_id');
	}
	public function reviews()
	{
		return $this->hasMany('Models\Review', 'restaurant_id', 'id')->with('user')->where('is_active', 1);
	}
	public function themes()
	{
		return $this->hasMany('Models\RestaurantTheme', 'restaurant_id', 'id');
	}
	public function cuisines()
	{
		return $this->hasMany('Models\RestaurantCuisine', 'restaurant_id', 'id');
	}
	public function favorite()
	{
		return $this->belongsTo('Models\Favorite', 'id', 'restaurant_id');
	}
	public function user()
	{
		return $this->belongsTo('Models\User', 'user_id', 'id');
	}
	public function city()
	{
		return $this->belongsTo('Models\City', 'city_id', 'id');
	}
	public function country()
	{
		return $this->belongsTo('Models\Country', 'country_id', 'id');
	}
	public function payment()
	{
		return $this->hasMany('Models\RestaurantPayment', 'restaurant_id', 'id');
	}
}
