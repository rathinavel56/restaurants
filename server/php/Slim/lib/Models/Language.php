<?php
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Language extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'languages';
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
