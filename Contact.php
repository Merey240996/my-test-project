<?php
declare(strict_types=1);
namespace App\Classes;

use IO\SqlClass;

class Contact extends SqlClass
{
	protected $table = 'contacts';
	protected $primaryKey = 'pkid';
	public $incrementing = true;
	public $timestamps = false;

	protected $fillable = [
		'company_pkid',
		'user_pkid',
		'user_login',
		'email',
		'phone',
		'displayName',
		'is_deleted',
		'gmtimeAdd',
		'gmtimeChange',
		'ugmtimeAdd',
		'ugmtimeChange',
	];

	protected $guarded = ['pkid'];

	public function clients()
	{
		return $this->hasMany(Client::class, 'contact_pkid', 'pkid');
	}

	/**
	 * Находит существующий Contact для этой компании по приоритету
	 * user_pkid > email > phone, либо создаёт новый. Используется
	 * ClientApi при создании/сохранении клиента, и будет переиспользован
	 * консьюмером очереди (RabbitMQ/Kafka) при обработке user.registered —
	 * там просто user_pkid будет известен заранее и совпадёт по приоритету 1.
	 */
	public static function resolveOrCreate(
		int $company_pkid,
		?int $user_pkid,
		?string $user_login,
		?string $email,
		?string $phone,
		?string $displayName
	): self {
		$email = $email !== null ? trim($email) : null;
		$phone = $phone !== null ? trim($phone) : null;

		$query = static::where('company_pkid', $company_pkid)->where('is_deleted', 0);

		$found = null;
		if ($user_pkid) {
			$found = (clone $query)->where('user_pkid', $user_pkid)->first();
		}
		if (!$found && $email) {
			$found = (clone $query)->where('email', $email)->first();
		}
		if (!$found && $phone) {
			$found = (clone $query)->where('phone', $phone)->first();
		}

		if ($found) {
			// Дополняем контакт данными, которых у него ещё не было
			// (например, узнали user_pkid для ранее анонимного контакта).
			$dirty = false;
			if ($user_pkid && !$found->user_pkid) { $found->user_pkid = $user_pkid; $dirty = true; }
			if ($user_login && !$found->user_login) { $found->user_login = $user_login; $dirty = true; }
			if ($email && !$found->email) { $found->email = $email; $dirty = true; }
			if ($phone && !$found->phone) { $found->phone = $phone; $dirty = true; }
			if ($dirty) {
				$found->gmtimeChange = gmdate('Y-m-d H:i:s');
				$found->ugmtimeChange = time();
				$found->save();
			}
			return $found;
		}

		$contact = new static();
		$contact->company_pkid = $company_pkid;
		$contact->user_pkid = $user_pkid ?: null;
		$contact->user_login = $user_login;
		$contact->email = $email;
		$contact->phone = $phone;
		$contact->displayName = $displayName ?: ($user_login ?: 'Без имени');
		$contact->is_deleted = 0;
		$contact->gmtimeAdd = gmdate('Y-m-d H:i:s');
		$contact->gmtimeChange = gmdate('Y-m-d H:i:s');
		$contact->ugmtimeAdd = time();
		$contact->ugmtimeChange = time();
		$contact->save();

		return $contact;
	}

	public static function rules(): array
	{
		$arr = parent::rules();
		$arr['static']['actionSearch'] = 1;
		$arr['static']['actionGetByID'] = 1;
		$arr['static']['actionCreate'] = 1;
		$arr['static']['actionSave'] = 1;
		$arr['static']['actionDelete'] = 1;
		return $arr;
	}
}
