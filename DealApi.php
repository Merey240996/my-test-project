<?php
declare(strict_types=1);
namespace App\Api;

use IO\Constants as EIO;
use IO\Api\CrudApi;
use IO\Api\ApiResult;
use App\Classes\Deal;
use App\Classes\DealStage;
use App\Classes\UserRole;
use App\Classes\AuditLog;

class DealApi extends CrudApi
{
	public static function struct(): array
	{
		return [
			'class' => Deal::class,
			'fields' => [
				'pkid' => ['type' => 'int'],
				'company_pkid' => ['type' => 'int'],
				'name' => ['type' => 'string', 'label' => 'Название'],
				'description' => ['type' => 'string', 'label' => 'Описание'],
				'client_pkid' => ['type' => 'int', 'label' => 'Клиент'],
				'lead_pkid' => ['type' => 'int', 'label' => 'Лид'],
				'funnel_pkid' => ['type' => 'int', 'label' => 'Воронка'],
				'stage_pkid' => ['type' => 'int', 'label' => 'Этап'],
				'responsible_user' => ['type' => 'int', 'label' => 'Ответственный'],
				'contactPerson' => ['type' => 'string', 'label' => 'Контактное лицо'],
				'contactEmail' => ['type' => 'string', 'label' => 'Email'],
				'contactPhone' => ['type' => 'string', 'label' => 'Телефон'],
				'contactSite' => ['type' => 'string', 'label' => 'Сайт'],
				'amount' => ['type' => 'float', 'label' => 'Сумма'],
				'refusalReason' => ['type' => 'string', 'label' => 'Причина отказа'],
				'convertedOrderPkid' => ['type' => 'int', 'label' => 'Заказ'],
				'status' => ['type' => 'int', 'label' => 'Статус'],
				'is_deleted' => ['type' => 'int', 'virtual' => true],
				'gmtimeAdd' => ['type' => 'string', 'readonly' => true],
				'gmtimeChange' => ['type' => 'string', 'readonly' => true],
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
		$rules['static']['actionMove'] = 1;
		$rules['static']['actionLose'] = 1;
		$rules['static']['actionStats'] = 1;
		$rules['static']['actionFilterValues'] = 1;
		$rules['static']['actionRollback'] = 1;
		$rules['static']['actionCanRollback'] = 1;
		$rules['static']['actionCanDelete'] = 1;
		return $rules;
	}

	// Удаление сделки доступно только администратору (pkid 1) и РОП (pkid 2) —
	// см. App\Api\EnumApi::DEAL_DELETE_ROLE_PKIDS (единственный источник правды,
	// фронт получает тот же список через crm.api.enum.dealDeleteRolePkids).

	// Всегда скрываем удалённые
	public function onSearchFilter(array $filter): array
	{
		$filter['is_deleted'] = 0;
		return $filter;
	}

	// Переход считается "откатом", если он в точности совпадает с обратным
	// переходом из STATUS_ROLLBACK — той же картой, которой руководствуется
	// doRollback (см. LeadApi::isStatusRollback).
	private function isStatusRollback(int $from, int $to): bool
	{
		return $from !== $to && isset(EnumApi::DEAL_STATUS_ROLLBACK[$from]) && EnumApi::DEAL_STATUS_ROLLBACK[$from] === $to;
	}

	/**
	 * Обычное редактирование сделки (модалка DealsModal) тоже умеет менять
	 * status — тот же рычаг, что и doRollback, просто с другой стороны
	 * формы. Без этой проверки правило "откат — только администратору" из
	 * doRollback ничего не стоило бы: любой пользователь мог бы просто
	 * открыть сделку на редактирование и выставить статус на шаг назад
	 * через обычный Save.
	 */
	public function doSave(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$oldDeal = $pkid > 0 ? Deal::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]) : null;

		if ($pkid > 0 && $oldDeal) {
			$item = xarr($params, 'item', []);

			if (array_key_exists('status', $item)) {
				$currentStatus = (int) $oldDeal['status'];
				$newStatus = (int) $item['status'];

				if ($this->isStatusRollback($currentStatus, $newStatus)
					&& !$this->userCanRollbackDealStatus((int) $oldDeal['company_pkid'])
				) {
					return $result->showResult([], 'Откат статуса доступен только администратору', EIO::DENY);
				}
			}
		}

		$res = parent::doSave($result, $params);
		if ($res->error_code === EIO::OK) {
			try {
				$item = $res->toArray();
				$savedPkid = (int) xarr($item, 'pkid', $pkid);
				$userPkid = (int) $this->app()->session->getUserID();
				$companyPkid = (int) xarr($item, 'company_pkid', 1);
				$action = $pkid > 0 ? AuditLog::ACTION_UPDATE : AuditLog::ACTION_CREATE;
				AuditLog::record($companyPkid, $userPkid, AuditLog::ENTITY_DEAL, $savedPkid, $action, $oldDeal, $item);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	public function doCreate(ApiResult $result, array $params): ApiResult
	{
		$res = parent::doCreate($result, $params);
		if ($res->error_code === EIO::OK) {
			try {
				$item = $res->toArray();
				$pkid = (int) xarr($item, 'pkid', 0);
				$userPkid = (int) $this->app()->session->getUserID();
				$companyPkid = (int) xarr($item, 'company_pkid', 1);

				AuditLog::record($companyPkid, $userPkid, AuditLog::ENTITY_DEAL, $pkid, AuditLog::ACTION_CREATE, null, $item);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	public function onUpdateData(array $save_data, array $update_data): array
	{
		if (empty($this->item_pkid)) {
			$update_data['gmtimeAdd'] = gmdate('Y-m-d H:i:s');
			$update_data['ugmtimeAdd'] = time();
			$update_data['is_deleted'] = 0;
			$update_data['status'] = $update_data['status'] ?? 0;

			// См. LeadApi::onUpdateData — то же самое: пустое название
			// раньше писалось в БД как есть, генерируем здесь, до вставки.
			if (trim((string) ($update_data['name'] ?? '')) === '') {
				$update_data['name'] = 'Сделка от ' . gmdate('d.m.Y H:i');
			}
		}

		// Ответственный обязателен как при создании, так и при сохранении
		$responsible = $update_data['responsible_user'] ?? ($save_data['responsible_user'] ?? null);
		if (empty($responsible)) {
			throw new \RuntimeException('Не указан ответственный');
		}

		if (array_key_exists('status', $update_data) && (int) $update_data['status'] !== 2) {
			$update_data['refusalReason'] = null;
		}

		$update_data['gmtimeChange'] = gmdate('Y-m-d H:i:s');
		$update_data['ugmtimeChange'] = time();
		return $update_data;
	}

	public function doSearch(ApiResult $result, array $params): ApiResult
	{
		try {
			$search = trim((string) xarr($params, 'search', ''));

			$rawFilter = $params['filter'] ?? [];
			$scalarFilter = [];
			$columnFilterObjects = [];

			foreach ($rawFilter as $key => $value) {
				if (is_array($value) && isset($value['op'])) {
					$columnFilterObjects[$key] = $value;
				} else {
					$scalarFilter[$key] = $value;
				}
			}

			$search_params = [
				'filter' => $scalarFilter,
				'fields' => ['*'],
				'join' => [],
				'order' => $params['order'] ?? ['pkid' => 'desc'],
				'start' => (int) ($params['start'] ?? 0),
				'limit' => (int) ($params['limit'] ?? 10),
			];

			$search_filter = $search_params['filter'];
			$search_filter = static::convertSearchFilterToDb($search_filter);
			$search_filter = $this->onSearchFilter($search_filter);
			$search_filter = $this->applyColumnFilters($search_filter, $columnFilterObjects);

			if ($search !== '') {
				$clientPkids = \App\Classes\Client::where('is_deleted', 0)
					->where(function ($q) use ($search) {
						$q->where('company', 'like', '%' . $search . '%')
						  ->orWhere('email', 'like', '%' . $search . '%')
						  ->orWhere('phone', 'like', '%' . $search . '%')
						  ->orWhere('user_login', 'like', '%' . $search . '%');
					})
					->pluck('pkid')
					->map(fn($v) => (int) $v)
					->unique()
					->values()
					->toArray();

				$orConditions = [
					['%name' => $search],
					['%contactPerson' => $search],
					['%contactEmail' => $search],
				];

				if (!empty($clientPkids)) {
					$orConditions[] = ['client_pkid' => $clientPkids];
				}

				$search_filter['$and'][] = ['$or' => $orConditions];
			}

			$search_params['filter'] = $search_filter;

			$arr = Deal::search($search_params);
			$items = $arr['res'] ?? [];
			$total = $arr['count'] ?? 0;

			// Подмешиваем stage_name
			if (!empty($items)) {
				$stagePkids = array_values(array_unique(array_filter(
					array_map(fn($row) => (int) ($row['stage_pkid'] ?? 0), $items)
				)));

				if (!empty($stagePkids)) {
					$stages = DealStage::whereIn('pkid', $stagePkids)
						->where('is_deleted', 0)
						->get(['pkid', 'name']);

					$stageNames = [];
					foreach ($stages as $stage) {
						$stageNames[(int) $stage->pkid] = $stage->name;
					}

					foreach ($items as &$row) {
						$pkid = (int) ($row['stage_pkid'] ?? 0);
						$row['stage_name'] = $stageNames[$pkid] ?? null;
					}
					unset($row);
				}
			}

			return $result->showSuccess(['items' => $items, 'total' => $total]);
		} catch (\Throwable $e) {
			return $result->showResult([], $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), EIO::VALIDATION);
		}
	}

	public function doMove(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$stage_pkid = (int) xarr($params, 'stage_pkid', 0);

		if ($pkid <= 0 || $stage_pkid <= 0) {
			return $result->showResult([], 'pkid и stage_pkid обязательны', EIO::VALIDATION);
		}

		$deal = Deal::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);
		if (!$deal) return $result->showResult([], 'Сделка не найдена', EIO::NOT_FOUND);

		$stage = DealStage::findItem(['filter' => ['pkid' => $stage_pkid, 'is_deleted' => 0]]);
		if (!$stage) return $result->showResult([], 'Этап не найден', EIO::VALIDATION);

		$ok = Deal::updateByFilter(
			['pkid' => $pkid],
			['stage_pkid' => $stage_pkid, 'gmtimeChange' => gmdate('Y-m-d H:i:s'), 'ugmtimeChange' => time()]
		);

		if ($ok !== 1) return $result->showResult([], 'Не удалось переместить сделку', EIO::ERROR);

		try {
			$userPkid = (int) $this->app()->session->getUserID();
			AuditLog::record(
				(int) $deal['company_pkid'],
				$userPkid,
				AuditLog::ENTITY_DEAL,
				$pkid,
				AuditLog::ACTION_STAGE_CHANGE,
				['stage_pkid' => $deal['stage_pkid']],
				['stage_pkid' => $stage_pkid]
			);
		} catch (\Throwable $e) {
			// ignore error
		}

		return $result->showSuccess(['pkid' => $pkid, 'stage_pkid' => $stage_pkid]);
	}

	// Проиграть сделку
	public function doLose(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$refusalReason = trim((string) xarr($params, 'refusalReason', ''));

		if ($pkid <= 0) {
			return $result->showResult([], 'pkid не передан', EIO::VALIDATION);
		}

		if ($refusalReason === '') {
			return $result->showResult([], 'Не указана причина проигрыша', EIO::VALIDATION);
		}

		$deal = Deal::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);
		if (!$deal) {
			return $result->showResult([], 'Сделка не найдена', EIO::NOT_FOUND);
		}

		$ok = Deal::updateByFilter(
			['pkid' => $pkid],
			[
				'status' => 2,
				'refusalReason' => $refusalReason,
				'gmtimeChange' => gmdate('Y-m-d H:i:s'),
				'ugmtimeChange' => time(),
			]
		);

		if ($ok !== 1) {
			return $result->showResult([], 'Не удалось обновить статус', EIO::ERROR);
		}

		try {
			$userPkid = (int) $this->app()->session->getUserID();
			AuditLog::record(
				(int) $deal['company_pkid'],
				$userPkid,
				AuditLog::ENTITY_DEAL,
				$pkid,
				AuditLog::ACTION_STAGE_CHANGE,
				['status' => $deal['status']],
				['status' => 2, 'refusalReason' => $refusalReason]
			);
		} catch (\Throwable $e) {
			// ignore error
		}

		return $result->showSuccess(['pkid' => $pkid, 'status' => 2, 'refusalReason' => $refusalReason]);
	}

	// Откат статуса сделки назад (Проиграна/Конвертирована в заказ → Открыта).
	// Доступно только пользователям с ролью "Администратор" (role_pkid =
	// ADMIN_ROLE_PKID) — см. App\Api\EnumApi::DEAL_STATUS_ROLLBACK.

	public function doRollback(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);

		if ($pkid <= 0) {
			return $result->showResult([], 'pkid не передан', EIO::VALIDATION);
		}

		$deal = Deal::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);

		if (!$deal) {
			return $result->showResult([], 'Сделка не найдена', EIO::NOT_FOUND);
		}

		if (!$this->userCanRollbackDealStatus((int) $deal['company_pkid'])) {
			return $result->showResult([], 'Откат статуса доступен только администратору', EIO::VALIDATION);
		}

		$currentStatus = (int) $deal['status'];

		if (!isset(EnumApi::DEAL_STATUS_ROLLBACK[$currentStatus])) {
			return $result->showResult([], 'Для текущего статуса сделки откат недоступен', EIO::VALIDATION);
		}

		$prevStatus = EnumApi::DEAL_STATUS_ROLLBACK[$currentStatus];

		$updateData = [
			'status' => $prevStatus,
			'gmtimeChange' => gmdate('Y-m-d H:i:s'),
			'ugmtimeChange' => time(),
		];

		// Возврат из "Проиграна" — причина проигрыша больше не актуальна.
		if ($currentStatus === 2) {
			$updateData['refusalReason'] = '';
		}

		$ok = Deal::updateByFilter(['pkid' => $pkid], $updateData);

		if ($ok !== 1) {
			return $result->showResult([], 'Не удалось откатить статус сделки', EIO::ERROR);
		}

		try {
			$userPkid = (int) $this->app()->session->getUserID();
			AuditLog::record(
				(int) $deal['company_pkid'],
				$userPkid,
				AuditLog::ENTITY_DEAL,
				$pkid,
				AuditLog::ACTION_STAGE_CHANGE,
				['status' => $currentStatus],
				['status' => $prevStatus]
			);
		} catch (\Throwable $e) {
			// ignore error
		}

		return $result->showSuccess([
			'pkid' => $pkid,
			'status' => $prevStatus,
		]);
	}

	/**
	 * @return array<int> pkid ролей, назначенных текущему пользователю в компании
	 */
	private function getCurrentUserRolePkids(int $company_pkid): array
	{
		$user_pkid = (int) $this->app()->session->getUserID();

		if ($company_pkid <= 0 || $user_pkid <= 0) {
			return [];
		}

		return UserRole::where('company_pkid', $company_pkid)
			->where('user_pkid', $user_pkid)
			->where('is_deleted', 0)
			->pluck('role_pkid')
			->map(fn ($v) => (int) $v)
			->unique()
			->values()
			->all();
	}

	// pkid роли "Администратор" — единственной роли, которой разрешён откат
	// статуса сделки назад (см. userCanRollbackDealStatus, App\Api\EnumApi::ADMIN_ROLE_PKID).

	private function userCanRollbackDealStatus(int $company_pkid): bool
	{
		$role_pkids = $this->getCurrentUserRolePkids($company_pkid);

		return in_array(EnumApi::ADMIN_ROLE_PKID, $role_pkids, true);
	}

	// Лёгкий эндпоинт для фронта — показывать ли возможность отката статуса.
	// Бэкенд всё равно перепроверяет право в doRollback, это только для UI.
	public function doCanRollback(ApiResult $result, array $params): ApiResult
	{
		$company_pkid = (int) xarr($params, 'company_pkid', 0);

		return $result->showSuccess([
			'canRollback' => $this->userCanRollbackDealStatus($company_pkid),
		]);
	}

	private function userCanDeleteDeal(int $company_pkid): bool
	{
		$role_pkids = $this->getCurrentUserRolePkids($company_pkid);
		return !empty(array_intersect(EnumApi::DEAL_DELETE_ROLE_PKIDS, $role_pkids));
	}

	// Лёгкий эндпоинт для фронта — показывать ли кнопку "Удалить".
	// Бэкенд всё равно перепроверяет право в doDelete, это только для UI.
	public function doCanDelete(ApiResult $result, array $params): ApiResult
	{
		$company_pkid = (int) xarr($params, 'company_pkid', 0);
		return $result->showSuccess([
			'canDelete' => $this->userCanDeleteDeal($company_pkid),
		]);
	}

	// Удаление сделки доступно только администратору (pkid 1) и РОП (pkid 2) —
	// см. DELETE_ROLE_PKIDS/userCanDeleteDeal. Бэкенд перепроверяет право
	// здесь независимо от doCanDelete, который отдаёт флаг только для UI.
	public function doDelete(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$oldDeal = $pkid > 0 ? Deal::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]) : null;

		if ($pkid > 0) {
			$deal = Deal::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);

			if ($deal) {
				$company_pkid = (int) $deal['company_pkid'];

				if (!$this->userCanDeleteDeal($company_pkid)) {
					return $result->showResult([], 'Нет прав на удаление сделок', EIO::DENY);
				}
			}
		}

		$res = parent::doDelete($result, $params);
		if ($res->error_code === EIO::OK && $oldDeal) {
			try {
				$userPkid = (int) $this->app()->session->getUserID();
				$companyPkid = (int) xarr($oldDeal, 'company_pkid', 1);

				AuditLog::record($companyPkid, $userPkid, AuditLog::ENTITY_DEAL, $pkid, AuditLog::ACTION_DELETE, $oldDeal, null);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	public function doStats(ApiResult $result, array $params): ApiResult
	{
		$companyPkid = (int) xarr($params, 'company_pkid', 0);
		$funnelPkid  = (int) xarr($params, 'funnel_pkid', 0);

		$base = Deal::where('company_pkid', $companyPkid)
			->where('funnel_pkid', $funnelPkid)
			->where('is_deleted', 0);

		// В работе (status = 0)
		$inProgress = (clone $base)->where('status', 0);
		$inProgressCount = $inProgress->count();
		$inProgressSum   = $inProgress->sum('amount');

		// Проигранные (status = 2)
		$lost = (clone $base)->where('status', 2);
		$lostCount = $lost->count();
		$lostSum   = $lost->sum('amount');

		// Конвертированные в заказ (status = 3)
		$converted = (clone $base)->where('status', 3);
		$convertedCount = $converted->count();
		$convertedSum   = $converted->sum('amount');

		$totalCount = (clone $base)->count();
		$totalSum   = (clone $base)->sum('amount');

		// Топ-3 причины проигрыша (по тексту refusalReason)
		$topStallRows = (clone $base)->where('status', 2)
			->selectRaw('refusalReason, COUNT(*) as cnt')
			->whereNotNull('refusalReason')
			->where('refusalReason', '!=', '')
			->groupBy('refusalReason')
			->orderByDesc('cnt')
			->limit(3)
			->get();

		$topStall = [];
		foreach ($topStallRows as $row) {
			$topStall[] = [
				'reason' => $row->refusalReason,
				'count'  => (int) $row->cnt,
				'pct'    => $lostCount ? round($row->cnt / $lostCount * 100) : 0,
			];
		}

		// NEW: распределение по этапам воронки — считаем по ВСЕЙ выборке (а не по текущей
		// странице таблицы), иначе конверсия на фронте искажается пагинацией
		$funnelStagesRows = (clone $base)
			->selectRaw('stage_pkid, COUNT(*) as cnt')
			->groupBy('stage_pkid')
			->get();
		$funnelStages = [];
		foreach ($funnelStagesRows as $row) {
			$funnelStages[(int) $row->stage_pkid] = (int) $row->cnt;
		}

		// NEW: распределение по ответственным менеджерам — аналог "распределения по
		// источникам" у лидов. У сделки нет source, зато responsible_user обязателен всегда.
		$managerRows = (clone $base)
			->selectRaw('responsible_user, COUNT(*) as cnt')
			->whereNotNull('responsible_user')
			->groupBy('responsible_user')
			->orderByDesc('cnt')
			->get();
		$managerDistribution = [];
		foreach ($managerRows as $row) {
			$managerDistribution[(int) $row->responsible_user] = (int) $row->cnt;
		}

		// NEW: сумма amount по каждому статусу (для донат-графика структуры)
		$byStatusSumRows = (clone $base)
			->selectRaw('status, SUM(amount) as total')
			->groupBy('status')
			->get();
		$byStatusSum = [];
		foreach ($byStatusSumRows as $row) {
			$byStatusSum[(int) $row->status] = (float) $row->total;
		}

		// NEW: средний чек сделки по всей воронке
		$avgAmount = $totalCount ? round($totalSum / $totalCount) : 0;

		// NEW: динамика сделок по месяцам за последние 6 месяцев
		$monthlyRows = (clone $base)
			->selectRaw("DATE_FORMAT(gmtimeAdd, '%Y-%m') as ym, COUNT(*) as cnt")
			->where('gmtimeAdd', '>=', gmdate('Y-m-01', strtotime('-5 months')))
			->groupBy('ym')
			->orderBy('ym')
			->get();

		$monthlyMap = [];
		foreach ($monthlyRows as $row) {
			$monthlyMap[$row->ym] = (int) $row->cnt;
		}

		$monthCategories = [];
		$monthValues = [];
		$ruMonths = ['01' => 'Янв', '02' => 'Фев', '03' => 'Мар', '04' => 'Апр', '05' => 'Май', '06' => 'Июн',
					'07' => 'Июл', '08' => 'Авг', '09' => 'Сен', '10' => 'Окт', '11' => 'Ноя', '12' => 'Дек'];
		for ($i = 5; $i >= 0; $i--) {
			$ym = gmdate('Y-m', strtotime("-{$i} months"));
			$monthCategories[] = $ruMonths[substr($ym, 5, 2)] ?? $ym;
			$monthValues[] = $monthlyMap[$ym] ?? 0;
		}

		return $result->showSuccess([
			'inProgress' => ['count' => $inProgressCount, 'sum' => $inProgressSum],
			'lost' => ['count' => $lostCount, 'sum' => $lostSum],
			'converted' => ['count' => $convertedCount, 'sum' => $convertedSum],
			'topStall' => $topStall, // теперь массив (топ-3), а не одиночный объект/null
			'totalCount' => $totalCount,
			// NEW
			'totalSum' => $totalSum,
			'funnelStages' => $funnelStages,
			'managerDistribution' => $managerDistribution,
			'byStatusSum' => $byStatusSum,
			'avgAmount' => $avgAmount,
			'monthlyDynamics' => [
				'categories' => $monthCategories,
				'values' => $monthValues,
			],
		]);
	}

	public function doFilterValues(ApiResult $result, array $params): ApiResult
	{
		$companyPkid = (int) xarr($params, 'company_pkid', 0);
		$funnelPkid  = (int) xarr($params, 'funnel_pkid', 0);

		$base = Deal::where('company_pkid', $companyPkid)
			->where('funnel_pkid', $funnelPkid)
			->where('is_deleted', 0);

		$statuses = (clone $base)
			->select('status')->distinct()
			->pluck('status')->map(fn($v) => (int) $v)->values()->toArray();

		$managers = (clone $base)
			->select('responsible_user')->whereNotNull('responsible_user')
			->distinct()->pluck('responsible_user')->values()->toArray();

		return $result->showSuccess([
			'statuses' => $statuses,
			'managers' => $managers,
		]);
	}

	private function applyColumnFilters(array $search_filter, array $columnFilterObjects): array
	{
		$fieldMap = [
			'manager' => 'responsible_user',
			'status'  => 'status',
		];

		foreach ($columnFilterObjects as $key => $cond) {
			$field = $fieldMap[$key] ?? $key;
			$op = $cond['op'] ?? null;

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
					$search_filter['$and'][] = ['$or' => [['=' . $field => null], ['=' . $field => '']]];
					break;
				case 'notEmpty':
					$search_filter['$and'][] = ['!=' . $field => null];
					$search_filter['$and'][] = ['!=' . $field => ''];
					break;
			}
		}
		return $search_filter;
	}
}
