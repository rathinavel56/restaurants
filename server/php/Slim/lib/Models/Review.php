<?php
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Review extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'reviews';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'user_id',
		'restaurant_id',
		'comments',
		'rating'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'user_id' => 'sometimes|required'
    );
	public function user()
	{
		return $this->belongsTo('Models\User', 'user_id', 'id')->select('id', 'first_name')->with('attachment');
	}
}
