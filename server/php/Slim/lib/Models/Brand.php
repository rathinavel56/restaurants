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

class Brand extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'brands';
    protected $fillable = array(
        'id',
        'name'
    );
    public $rules = array(
        'name' => 'sometimes|required'
    );
	public function attachments()
    {
        return $this->hasMany('Models\Attachment', 'foreign_id', 'id')->where('class', 'Brand');
    }
	public function attachment()
    {
        return $this->hasOne('Models\Attachment', 'foreign_id', 'id')->where('class', 'Brand');
    }
}
