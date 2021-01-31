<?php
/**
 * City
 */
namespace Models;

use Illuminate\Database\Eloquent\Relations\Relation;

class Theme extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'themes';
	public $hidden = array(
        'created_at',
        'updated_at',
		'slug',
		'is_active'
    );
    protected $fillable = array(
        'id',
		'user_id',
		'created_at',
		'updated_at',
		'name',
		'count',
		'slug',
		'is_active'
    );
    public $rules = array(
        'id' => 'sometimes|required',
		'created_at' => 'sometimes|required',
		'updated_at' => 'sometimes|required',
		'name' => 'sometimes|required',
		'description' => 'sometimes|required',
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
	public function attachments()
    {
        return $this->hasMany('Models\Attachment', 'foreign_id', 'id')->where('class', 'Theme');
    }
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'Theme');
    }
}
