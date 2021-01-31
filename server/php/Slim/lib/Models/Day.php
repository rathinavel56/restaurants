<?php
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Day extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'days';
	public $hidden = array(
        'created_at',
        'updated_at',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'created_at',
		'updated_at',
		'name'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required'
    );
}
