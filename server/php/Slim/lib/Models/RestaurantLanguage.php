<?php
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class RestaurantLanguage extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'restaurant_languages';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'restaurant_id',
		'name'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required',
		'name' => 'sometimes|required'
    );
	public function language()
	{
		return $this->belongsTo('Models\Language', 'language_id', 'id');
	}
}
