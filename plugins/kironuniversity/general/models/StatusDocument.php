<?php namespace Kironuniversity\General\Models;

use Model;

/**
 * StatusDocument Model
 */
class StatusDocument extends Model
{

    /**
     * @var string The database table used by the model.
     */
    public $table = 'status_document';

    public $connection = 'plan';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    protected function getDateFormat(){
      return 'Y-m-d H:i:sO';
    }

}
