<?php
declare(strict_types=1);
namespace App\Classes;

use IO\SqlClass;

class ChatChannel extends SqlClass
{
	protected $table = 'chat_channels';
	protected $primaryKey = 'pkid';
	public $incrementing = true;
	public $timestamps = false;

	protected $fillable = [
		'company_pkid', 'type', 'name', 'token', 'secret', 'status', 'last_error',
		'is_deleted', 'gmtimeAdd', 'gmtimeChange', 'ugmtimeAdd', 'ugmtimeChange',
	];

	protected $guarded = ['pkid'];
}
