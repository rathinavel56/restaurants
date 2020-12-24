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

class Center extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'testing_centers';
    protected $fillable = array(
        'name',
        'lat',
        'lon',
        'is_active'
    );
    public $rules = array(
        'name' => 'sometimes|required',
        'lat' => 'sometimes|required',
        'lon' => 'sometimes|required|email',
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
            $query->orWhereHas('name', function ($q) use ($params) {
                $q->where('name', 'ilike', '%' . $params['q'] . '%');
            });
        }
    }
	public function island()
    {
        return $this->belongsTo('Models\Island', 'island_id', 'id');
    }
}
