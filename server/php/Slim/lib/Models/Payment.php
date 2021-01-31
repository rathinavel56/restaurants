<?php
/**
 * Payment
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    GETLANCERV3
 * @subpackage Model
 */
namespace Models;

class Payment extends AppModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'payment_options';
    protected $fillable = array(
        'created_at',
        'updated_at',
        'name',
        'is_active'
    );
}
