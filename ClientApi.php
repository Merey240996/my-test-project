<?php
declare(strict_types=1);
namespace App\Api;

use IO\Constants as EIO;
use IO\Api\CrudApi;
use IO\Api\ApiResult;
use App\Classes\Client;
use App\Classes\UserRole;
use App\Classes\ClientBasePermissions;
use App\Classes\RolePermission;
use App\Classes\ClientFieldValues;
use App\Classes\Contact;

class ClientApi extends CrudApi
{
	private const RESOURCE_TYPE = 'core_crm_funnels';

	/**
	 * contact_pkid резолвится в doCreate/doSave (там, где мы и так уже
	 * трогаем БД для проверки дублей) и переносится в реальный update_data
	 * через onUpdateData — так же, как это уже сделано для gmtimeChange
	 * чуть ниже. Через $params['item'] это поле не идёт: оно 'readonly'
	 * в struct(), и есть риск, что фреймворк фильтрует readonly-поля из
	 * item до сохранения.
	 */
	private ?int $resolvedContactPkid = null;

	public static function struct(): array
	{
		return [
			'class' => Client::class,
			'fields' => [
				'pkid' => ['type' => 'int'],
				'company_pkid' => ['type' => 'int'],
				'base_pkid' => ['type' => 'int', 'label' => 'База клиентов'],
				'user_pkid' => ['type' => 'int', 'label' => 'Зарегистрированный пользователь'],
				'user_login' => ['type' => 'string', 'label' => 'Логин пользователя'],
				'contact_pkid' => ['type' => 'int', 'label' => 'Контакт (единая идентичность)', 'readonly' => true],
				'company' => ['type' => 'string', 'label' => 'Компания'],
				'email' => ['type' => 'string', 'label' => 'Email'],
				'phone' => ['type' => 'string', 'label' => 'Телефон'],
				'date' => ['type' => 'string', 'label' => 'Дата'],
				'is_deleted' => ['type' => 'int', 'virtual' => true],
			],
			'primary' => ['pkid'],
			'soft_delete' => true,
		];
	}

	public static function rules(): array
	{
		$rules = parent::rules();

		$rules['static']['actionSearch'] = 1;
		$rules['static']['actionGetByID'] = 1;
		$rules['static']['actionCreate'] = 1;
		$rules['static']['actionSave'] = 1;
		$rules['static']['actionDelete'] = 1;
		$rules['static']['actionFindUserByLogin'] = 1;
		return $rules;
	}

	public function onInit(): int
	{
		$session = $this->app()->session;
		if (!$session || !$session->isAuth()) {
			return EIO::DENY;
		}
		return EIO::OK;
	}

	public function onSearchFilter(array $filter): array
	{
		$filter['is_deleted'] = 0;
		return $filter;
	}

	public function doSearch(ApiResult $result, array $params): ApiResult
	{
		try{

		
		$class_name = static::getSqlClass();

		$rawFilter = $params['filter'] ?? [];

		// Разделяем: скаляры (company_pkid, base_pkid) идут в convertSearchFilterToDb,
		// объекты {op,...} с фронта обрабатываем сами
		$scalarFilter = [];
		$columnFilterObjects = [];
		foreach ($rawFilter as $key => $value) {
			if (is_array($value) && isset($value['op'])) {
				$columnFilterObjects[$key] = $value;
			} else {
				$scalarFilter[$key] = $value;
			}
		}

		$search_params = $this->onSearchParams([
			'filter' => $scalarFilter,
			'fields' => ['*'],
			'join' => [],
			'order' => $params['order'] ?? ['pkid' => 'desc'],
			'start' => (int) ($params['start'] ?? 0),
			'limit' => (int) ($params['limit'] ?? 50),
		]);

		$search_filter = $search_params['filter'];
		$search_filter = static::convertSearchFilterToDb($search_filter);
		$search_filter = $this->onSearchFilter($search_filter);

		// NEW: колоночные фильтры (company, email, phone, createdAt) — добавляем
		// напрямую в дерево, минуя convertSearchFilterToDb
		$base_pkid_for_cf = (int) ($scalarFilter['base_pkid'] ?? 0);
		$search_filter = $this->applyColumnFilters($search_filter, $columnFilterObjects, $base_pkid_for_cf);

		// существующий глобальный поиск — без изменений
		$searchTerm = trim((string) ($params['search'] ?? ''));
		if ($searchTerm !== '') {
			$orConditions = [
				['%company' => $searchTerm],
				['%email' => $searchTerm],
				['%phone' => $searchTerm],
				['%user_login' => $searchTerm], 
			];
			if (preg_match('/^(?:CL-?)?(\d+)$/i', $searchTerm, $m)) {
				$orConditions[] = ['pkid' => (int) $m[1]];
			}
			$search_filter['$and'][] = ['$or' => $orConditions];
		}

		$search_params['filter'] = $search_filter;

		$arr = $class_name::search($search_params);
		$items = $arr['res'] ?? [];
		$total = $arr['count'] ?? 0;

		return $result->showSuccess([
			'items' => $items,
			'total' => $total,
		]);
		} catch (\Throwable $e) {
			return $result->showResult([], $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), EIO::VALIDATION);
		}
	}

	/**
	 * base_pkid/company_pkid — FK, задаются только при создании и неизменны
	 * после этого. На update — намеренно игнорируем их, даже если фронт
	 * (или прямой вызов API) их передал: клиент не может "переехать" в
	 * другую базу — при необходимости заводится новая запись клиента в
	 * нужной базе. Это также гарантирует, что агрегация leads/deals по
	 * base_pkid через join с clients остаётся исторически корректной.
	 * На create (item_pkid ещё пуст) — наоборот, обязаны их сохранить,
	 * иначе клиент создаётся без базы/компании (см. unset ниже).
	 */
	public function onUpdateData(array $save_data, array $update_data): array
	{
		if (!empty($this->item_pkid)) {
			// Это реальный update существующей записи — base_pkid/company_pkid
			// неизменны после создания (см. докблок класса). При create же
			// (item_pkid ещё пуст) их наоборот нужно сохранить — иначе
			// клиент создаётся без базы и без компании.
			unset($update_data['base_pkid'], $update_data['company_pkid']);
		}

		$update_data['gmtimeChange'] = gmdate('Y-m-d H:i:s');
		$update_data['ugmtimeChange'] = time();

		if ($this->resolvedContactPkid !== null) {
			$update_data['contact_pkid'] = $this->resolvedContactPkid;
		}

		if (empty($this->item_pkid)) {
			$update_data['gmtimeAdd'] = gmdate('Y-m-d H:i:s');
			$update_data['ugmtimeAdd'] = time();
			$update_data['is_deleted'] = 0;
		}

		return $update_data;
	}

	public function doCreate(ApiResult $result, array $params): ApiResult
	{
		$item = xarr($params, 'item', []);
		$base_pkid = (int) xarr($item, 'base_pkid', 0);
		$company_pkid = (int) xarr($item, 'company_pkid', 0);

		if ($base_pkid <= 0) {
			return $result->showResult([], 'base_pkid не передан', EIO::VALIDATION);
		}

		if (!$this->hasAccessToBase($base_pkid, $company_pkid)) {
			return $result->showResult([], 'Нет доступа к этой базе', EIO::DENY);
		}

		if (!$this->can('canCreate', $company_pkid)) {
			return $result->showResult([], 'Нет прав на добавление клиентов', EIO::DENY);
		}

		if ($this->findDuplicate($base_pkid, $item)) {
			return $result->showResult([], 'Клиент с таким email/телефоном/логином уже есть в этой базе', EIO::VALIDATION);
		}

		$deleted = $this->findDeletedDuplicate($base_pkid, $item);
		if ($deleted) {
			return $this->restoreClient($result, $deleted, $item);
		}

		// Резолвим единый Contact (по user_pkid > email > phone в рамках
		// company_pkid), создаём новый если не нашли ни одного совпадения.
		// contact_pkid не принимается от фронта (см. 'readonly' в struct()) —
		// это всегда наш серверный расчёт, иначе клиент мог бы "приклеить"
		// свою запись к чужому контакту.
		$contact = Contact::resolveOrCreate(
			$company_pkid,
			(int) xarr($item, 'user_pkid', 0) ?: null,
			xarr($item, 'user_login', null),
			xarr($item, 'email', null),
			xarr($item, 'phone', null),
			xarr($item, 'company', null)
		);
		$this->resolvedContactPkid = (int) $contact->pkid;

		$res = parent::doCreate($result, $params);
		if ($res->error_code === EIO::OK) {
			try {
				$newItem = $res->toArray();
				$savedPkid = (int) xarr($newItem, 'pkid', 0);
				$session = $this->app()->session;
				$userPkid = (int) ($session ? ($session->getUserID() ?? 0) : 0);
				$compPkid = (int) xarr($newItem, 'company_pkid', $company_pkid ?: 1);

				\App\Classes\AuditLog::record($compPkid, $userPkid, \App\Classes\AuditLog::ENTITY_CLIENT, $savedPkid, \App\Classes\AuditLog::ACTION_CREATE, null, $newItem);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	public function doSave(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$oldModel = $pkid > 0 ? Client::findItem(['filter' => ['pkid' => $pkid]]) : null;

		if ($pkid > 0) {
			$model = Client::where('pkid', $pkid)
				->where('is_deleted', 0)
				->first();

			if ($model) {
				$company_pkid = (int) $model->company_pkid;
				$base_pkid = (int) $model->base_pkid;

				if (!$this->hasAccessToBase($base_pkid, $company_pkid)) {
					return $result->showResult([], 'Нет доступа к этой базе', EIO::DENY);
				}

				if (!$this->can('canEdit', $company_pkid)) {
					return $result->showResult([], 'Нет прав на редактирование клиентов', EIO::DENY);
				}

				$item = xarr($params, 'item', []);

				if ($this->findDuplicate($base_pkid, $item, $pkid)) {
					return $result->showResult([], 'Клиент с таким email/телефоном/логином уже есть в этой базе', EIO::VALIDATION);
				}

				// NEW: проверка на конфликт с УДАЛЁННОЙ записью того же user_pkid/email/phone
				$deleted = $this->findDeletedDuplicate($base_pkid, $item, $pkid);
				if ($deleted) {
					return $result->showResult(
						['deleted_pkid' => (int) $deleted->pkid],
						'Этот пользователь ранее уже был клиентом в этой базе и был удалён (запись #' . $deleted->pkid . '). Восстановите старую запись вместо привязки к текущей.',
						EIO::VALIDATION
					);
				}

				if (empty($model->contact_pkid)) {
					$contact = Contact::resolveOrCreate(
						$company_pkid,
						(int) xarr($item, 'user_pkid', $model->user_pkid) ?: null,
						xarr($item, 'user_login', $model->user_login),
						xarr($item, 'email', $model->email),
						xarr($item, 'phone', $model->phone),
						xarr($item, 'company', $model->company)
					);
					$this->resolvedContactPkid = (int) $contact->pkid;
				}
			}
		}

		$res = parent::doSave($result, $params);
		if ($res->error_code === EIO::OK) {
			try {
				$newItem = $res->toArray();
				$savedPkid = (int) xarr($newItem, 'pkid', $pkid);
				$session = $this->app()->session;
				$userPkid = (int) ($session ? ($session->getUserID() ?? 0) : 0);
				$companyPkid = (int) xarr($newItem, 'company_pkid', 1);

				$action = $pkid > 0 ? \App\Classes\AuditLog::ACTION_UPDATE : \App\Classes\AuditLog::ACTION_CREATE;
				\App\Classes\AuditLog::record($companyPkid, $userPkid, \App\Classes\AuditLog::ENTITY_CLIENT, $savedPkid, $action, $oldModel, $newItem);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	public function doDelete(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$oldModel = $pkid > 0 ? Client::findItem(['filter' => ['pkid' => $pkid]]) : null;

		if ($pkid > 0) {
			$model = Client::where('pkid', $pkid)
				->where('is_deleted', 0)
				->first();

			if ($model) {
				$company_pkid = (int) $model->company_pkid;

				if (!$this->hasAccessToBase((int) $model->base_pkid, $company_pkid)) {
					return $result->showResult([], 'Нет доступа к этой базе', EIO::DENY);
				}

				if (!$this->can('canDelete', $company_pkid)) {
					return $result->showResult([], 'Нет прав на удаление клиентов', EIO::DENY);
				}
			}
		}

		$res = parent::doDelete($result, $params);
		if ($res->error_code === EIO::OK && $oldModel) {
			try {
				$session = $this->app()->session;
				$userPkid = (int) ($session ? ($session->getUserID() ?? 0) : 0);
				$companyPkid = (int) xarr($oldModel, 'company_pkid', 1);

				\App\Classes\AuditLog::record($companyPkid, $userPkid, \App\Classes\AuditLog::ENTITY_CLIENT, $pkid, \App\Classes\AuditLog::ACTION_DELETE, $oldModel, null);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	/**
	* Поиск зарегистрированного пользователя платформы по логину —
	* для подстановки company/email/phone в форму клиента.
	*
	* ВНИМАНИЕ: entity 'com.class.account.findAccountByLogin' — по аналогии
	* с com.class.account.findAccountsByCompanyID из RoleApi::doGetEmployees.
	* Проверьте точное имя метода в com/class/account на своей стороне.
	*/
	public function doFindUserByLogin(ApiResult $result, array $params): ApiResult
	{
		$login = trim((string) xarr($params, 'login', ''));

		if ($login === '') {
			return $result->showResult([], 'Логин не указан', EIO::VALIDATION);
		}

		$user = \IO\Core::call(
			'com.class.user.findByLogin',
			['login' => $login]
		);

		// структура ответа как в старом коде: результат — массив, проиндексированный по логину
		$user = xarr($user, $login, []);

		if (empty($user)) {
			return $result->showResult([], 'Пользователь не зарегистрирован в системе', EIO::NOTFOUND_OBJECT);
		}

		return $result->showSuccess([
			'user' => [
				'pkid'    => (int) xarr($user, 'id', 0),
				'login'   => $login,
				'company' => xarr($user, 'company', ''),
				'email'   => xarr($user, 'email', ''),
				'phone'   => xarr($user, 'tel', xarr($user, 'phone', '')), // в doGetUserPhone это поле называлось 'tel'
			],
		]);
	}

	private function applyColumnFilters(array $search_filter, array $columnFilterObjects, int $base_pkid_for_cf = 0): array
	{
		foreach ($columnFilterObjects as $key => $cond) {
			if (str_starts_with($key, 'cf_')) {
				$field_pkid = (int) substr($key, 3);
				if ($field_pkid <= 0) {
					continue;
				}
				$search_filter = $this->applyCustomFieldFilter($search_filter, $field_pkid, $cond, $base_pkid_for_cf);
				continue;
			}

			$field = $key;
			$op = $cond['op'] ?? null;

			// createdAt на фронте — вычисляемое поле; реальная колонка — ugmtimeAdd
			// (unix-секунды). DateFilter шлёт ms-эпоху в from/to.
			$isCreatedAt = ($field === 'createdAt');
			if ($isCreatedAt) {
				$field = 'gmtimeAdd';
			}

			// DateFilter ВСЕГДА шлёт {op, from, to} — это надёжный признак
			// датового условия, независимо от имени поля (createdAt или cf_ типа "Дата").
			$isDateShaped = array_key_exists('from', $cond);

			if ($isDateShaped) {
				$toDateTimeString = fn($ms) => gmdate('Y-m-d H:i:s', (int) round(((float) $ms) / 1000));
				$normalize = fn($ms) => $isCreatedAt ? $toDateTimeString($ms) : $ms;

				switch ($op) {
					case 'empty':
						$search_filter['$and'][] = ['$or' => [
							['=' . $field => null],
							['=' . $field => ''],
						]];
						break;

					case 'notEmpty':
						$search_filter['$and'][] = ['!=' . $field => null];
						$search_filter['$and'][] = ['!=' . $field => ''];
						break;

					case 'equals':
						if (!empty($cond['from'])) {
							$search_filter['$and'][] = ['=' . $field => $normalize($cond['from'])];
						}
						break;

					case 'notEquals':
						if (!empty($cond['from'])) {
							$search_filter['$and'][] = ['!=' . $field => $normalize($cond['from'])];
						}
						break;

					case 'before':
						if (!empty($cond['from'])) {
							$search_filter['$and'][] = ['<' . $field => $normalize($cond['from'])];
						}
						break;

					case 'after':
						if (!empty($cond['from'])) {
							$search_filter['$and'][] = ['>' . $field => $normalize($cond['from'])];
						}
						break;

					case 'between':
						if (!empty($cond['from'])) {
							$search_filter['$and'][] = ['>=' . $field => $normalize($cond['from'])];
						}
						if (!empty($cond['to'])) {
							$search_filter['$and'][] = ['<=' . $field => $normalize($cond['to'])];
						}
						break;

					default:
						break;
				}
				continue;
			}

			// ---- всё остальное (FacetedFilter, RangeFilter) — без изменений ----
			switch ($op) {
				case 'equals':
					if (!empty($cond['values'])) {
						$or = array_map(fn($v) => ['=' . $field => $v], $cond['values']);
						$search_filter['$and'][] = ['$or' => $or];
					} elseif (isset($cond['value'])) {
						$search_filter['$and'][] = ['=' . $field => $cond['value']];
					}
					break;

				case 'notEquals':
					if (!empty($cond['values'])) {
						foreach ($cond['values'] as $v) {
							$search_filter['$and'][] = ['!=' . $field => $v];
						}
					} elseif (isset($cond['value'])) {
						$search_filter['$and'][] = ['!=' . $field => $cond['value']];
					}
					break;

				case 'empty':
					$search_filter['$and'][] = ['$or' => [
						['=' . $field => null],
						['=' . $field => ''],
					]];
					break;

				case 'notEmpty':
					$search_filter['$and'][] = ['!=' . $field => null];
					$search_filter['$and'][] = ['!=' . $field => ''];
					break;

				case 'gt':
					if (isset($cond['min'])) $search_filter['$and'][] = ['>' . $field => $cond['min']];
					break;
				case 'gte':
					if (isset($cond['min'])) $search_filter['$and'][] = ['>=' . $field => $cond['min']];
					break;
				case 'lt':
					$v = $cond['max'] ?? $cond['min'] ?? null;
					if ($v !== null) $search_filter['$and'][] = ['<' . $field => $v];
					break;
				case 'lte':
					$v = $cond['max'] ?? $cond['min'] ?? null;
					if ($v !== null) $search_filter['$and'][] = ['<=' . $field => $v];
					break;
				case 'between':
					if (isset($cond['min']) && $cond['min'] !== '') {
						$search_filter['$and'][] = ['>=' . $field => $cond['min']];
					}
					if (isset($cond['max']) && $cond['max'] !== '') {
						$search_filter['$and'][] = ['<=' . $field => $cond['max']];
					}
					break;

				default:
					break;
			}
		}

		return $search_filter;
	}

	private function applyCustomFieldFilter(array $search_filter, int $field_pkid, array $cond, int $base_pkid): array
	{
		$op = $cond['op'] ?? null;

		if ($op === 'empty') {
			// Клиент подходит, если ЛИБО нет строки в client_field_values,
			// ЛИБО строка есть, но значение пустое.
			// Реализуем через отрицание: исключаем тех, у кого есть непустое значение.
			$nonEmptyClientPkids = ClientFieldValues::where('field_pkid', $field_pkid)
				->where('isDeleted', 0)
				->where('value', '!=', '')
				->whereNotNull('value')
				->pluck('client_pkid')
				->map(fn ($v) => (int) $v)
				->unique()
				->values()
				->all();

			if (!empty($nonEmptyClientPkids)) {
				foreach ($nonEmptyClientPkids as $pkid) {
					$search_filter['$and'][] = ['!=pkid' => $pkid];
				}
			}
			// если $nonEmptyClientPkids пуст — все клиенты базы подходят,
			// дополнительных условий не нужно
			return $search_filter;
		}

		$matchingClientPkids = $this->queryCustomFieldClientPkids($field_pkid, $op, $cond);

		if (empty($matchingClientPkids)) {
			// ни один клиент не подходит — форсируем пустой результат
			$search_filter['$and'][] = ['=pkid' => -1];
			return $search_filter;
		}

		$or = array_map(fn ($pkid) => ['=pkid' => $pkid], $matchingClientPkids);
		$search_filter['$and'][] = ['$or' => $or];

		return $search_filter;
	}
	
	private function queryCustomFieldClientPkids(int $field_pkid, ?string $op, array $cond): array
	{
		$query = ClientFieldValues::where('field_pkid', $field_pkid)
			->where('isDeleted', 0);

		switch ($op) {
			case 'equals':
				if (!empty($cond['values'])) {
					$query->whereIn('value', $cond['values']);
				} elseif (isset($cond['value'])) {
					$query->where('value', $cond['value']);
				} else {
					return [];
				}
				break;

			case 'notEquals':
				// notEquals: клиент подходит, если значение ЕСТЬ и оно не равно —
				// клиентов без строки вообще эта операция не считает подходящими
				// (аналогично поведению equals для обычных полей)
				if (!empty($cond['values'])) {
					$query->whereNotIn('value', $cond['values']);
				} elseif (isset($cond['value'])) {
					$query->where('value', '!=', $cond['value']);
				} else {
					return [];
				}
				break;

			case 'notEmpty':
				$query->where('value', '!=', '')->whereNotNull('value');
				break;

			// ---- RangeFilter (числа/деньги). value хранится строкой,
			// поэтому сравниваем через CAST на уровне SQL. ----
			case 'gt':
				if (!isset($cond['min'])) return [];
				$query->whereRaw('CAST(value AS DECIMAL(18,4)) > ?', [$cond['min']]);
				break;
			case 'gte':
				if (!isset($cond['min'])) return [];
				$query->whereRaw('CAST(value AS DECIMAL(18,4)) >= ?', [$cond['min']]);
				break;
			case 'lt':
				$v = $cond['max'] ?? $cond['min'] ?? null;
				if ($v === null) return [];
				$query->whereRaw('CAST(value AS DECIMAL(18,4)) < ?', [$v]);
				break;
			case 'lte':
				$v = $cond['max'] ?? $cond['min'] ?? null;
				if ($v === null) return [];
				$query->whereRaw('CAST(value AS DECIMAL(18,4)) <= ?', [$v]);
				break;
			case 'between':
				$has = false;
				if (isset($cond['min']) && $cond['min'] !== '') {
					$query->whereRaw('CAST(value AS DECIMAL(18,4)) >= ?', [$cond['min']]);
					$has = true;
				}
				if (isset($cond['max']) && $cond['max'] !== '') {
					$query->whereRaw('CAST(value AS DECIMAL(18,4)) <= ?', [$cond['max']]);
					$has = true;
				}
				if (!$has) return [];
				break;

			// ---- DateFilter. value хранится строкой в формате даты. ----
			case 'before':
				if (empty($cond['value'])) return [];
				$query->where('value', '<', $cond['value']);
				break;
			case 'after':
				if (empty($cond['value'])) return [];
				$query->where('value', '>', $cond['value']);
				break;

			default:
				return [];
		}

		return $query->pluck('client_pkid')
			->map(fn ($v) => (int) $v)
			->unique()
			->values()
			->all();
	}				

	private function findDuplicate(int $base_pkid, array $item, int $exclude_pkid = 0): bool
	{
		$user_pkid = (int) xarr($item, 'user_pkid', 0);

		if ($user_pkid > 0) {
			return $this->existsInBase($base_pkid, 'user_pkid', $user_pkid, 0, $exclude_pkid);
		}

		$email = trim((string) xarr($item, 'email', ''));
		$phone = trim((string) xarr($item, 'phone', ''));

		if ($email === '' && $phone === '') {
			return false;
		}

		if ($email !== '' && $this->existsInBase($base_pkid, 'email', $email, 0, $exclude_pkid)) {
			return true;
		}
		if ($phone !== '' && $this->existsInBase($base_pkid, 'phone', $phone, 0, $exclude_pkid)) {
			return true;
		}

		return false;
	}

	private function findDeletedDuplicate(int $base_pkid, array $item, int $exclude_pkid = 0): ?Client
	{
		$user_pkid = (int) xarr($item, 'user_pkid', 0);

		if ($user_pkid > 0) {
			return $this->findOneInBase($base_pkid, 'user_pkid', $user_pkid, 1, $exclude_pkid);
		}

		$email = trim((string) xarr($item, 'email', ''));
		$phone = trim((string) xarr($item, 'phone', ''));

		if ($email !== '') {
			$found = $this->findOneInBase($base_pkid, 'email', $email, 1, $exclude_pkid);
			if ($found) return $found;
		}
		if ($phone !== '') {
			$found = $this->findOneInBase($base_pkid, 'phone', $phone, 1, $exclude_pkid);
			if ($found) return $found;
		}

		return null;
	}

	private function existsInBase(int $base_pkid, string $field, $value, int $is_deleted, int $exclude_pkid = 0): bool
	{
		$query = Client::where('base_pkid', $base_pkid)
			->where('is_deleted', $is_deleted)
			->where($field, $value);

		if ($exclude_pkid > 0) {
			$query->where('pkid', '!=', $exclude_pkid);
		}

		return $query->exists();
	}

	private function findOneInBase(int $base_pkid, string $field, $value, int $is_deleted, int $exclude_pkid = 0): ?Client
	{
		$query = Client::where('base_pkid', $base_pkid)
			->where('is_deleted', $is_deleted)
			->where($field, $value);

		if ($exclude_pkid > 0) {
			$query->where('pkid', '!=', $exclude_pkid);
		}

		return $query->first();
	}

	private function restoreClient(ApiResult $result, Client $model, array $item): ApiResult
	{
		$model->is_deleted = 0;
		$model->company = xarr($item, 'company', $model->company);
		$model->email = xarr($item, 'email', $model->email);
		$model->phone = xarr($item, 'phone', $model->phone);
		$model->user_login = xarr($item, 'user_login', $model->user_login);   // NEW
		$model->user_pkid = (int) xarr($item, 'user_pkid', $model->user_pkid);
		$model->gmtimeChange = gmdate('Y-m-d H:i:s');
		$model->ugmtimeChange = time();
		$model->save();

		return $result->showSuccess(['pkid' => (int) $model->pkid, 'restored' => true]);
	}

	private function hasAccessToBase(int $base_pkid, int $company_pkid): bool
	{
		$allowed = $this->getAllowedBasePkids($company_pkid);

		if ($allowed === null) {
			return true;
		}

		return in_array($base_pkid, $allowed, true);
	}

	private function getAllowedBasePkids(int $company_pkid): ?array
	{
		if ($company_pkid <= 0) {
			return [];
		}

		$user_pkid = (int) $this->app()->session->getUserID();

		if ($user_pkid <= 0) {
			return [];
		}

		$role_pkids = UserRole::where('company_pkid', $company_pkid)
			->where('user_pkid', $user_pkid)
			->where('is_deleted', 0)
			->pluck('role_pkid')
			->map(fn ($v) => (int) $v)
			->unique()
			->values()
			->all();

		if (empty($role_pkids)) {
			return [];
		}

		return ClientBasePermissions::where('company_pkid', $company_pkid)
			->whereIn('role_pkid', $role_pkids)
			->where('is_deleted', 0)
			->pluck('base_pkid')
			->map(fn ($v) => (int) $v)
			->unique()
			->values()
			->all();
	}

	private function getPermissionFlags(int $company_pkid): array
	{
		$empty = [
			'canRead' => false,
			'canCreate' => false,
			'canEdit' => false,
			'canDelete' => false,
			'canViewHistory' => false,
			'canExport' => false,
			'canConfigureFields' => false,
			'canBulkAction' => false,
		];

		if ($company_pkid <= 0) return $empty;

		$user_pkid = (int) $this->app()->session->getUserID();
		if ($user_pkid <= 0) return $empty;

		$role_pkids = UserRole::where('company_pkid', $company_pkid)
			->where('user_pkid', $user_pkid)
			->where('is_deleted', 0)
			->pluck('role_pkid')
			->map(fn ($v) => (int) $v)
			->unique()
			->values()
			->all();

		if (empty($role_pkids)) return $empty;

		$rows = RolePermission::where('company_pkid', $company_pkid)
			->whereIn('role_pkid', $role_pkids)
			->where('resource_type', self::RESOURCE_TYPE)
			->where('is_deleted', 0)
			->get();

		$flags = $empty;
		foreach ($rows as $row) {
			foreach ($flags as $key => $_) {
				if ((int) $row->$key === 1) {
					$flags[$key] = true;
				}
			}
		}

		return $flags;
	}

	private function can(string $permission, int $company_pkid): bool
	{
		$flags = $this->getPermissionFlags($company_pkid);
		return $flags[$permission] ?? false;
	}
}
