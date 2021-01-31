<?php
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Favorite extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'favorites';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'user_id'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'user_id' => 'sometimes|required',
		'restaurant_id' => 'sometimes|required'
    );
}
