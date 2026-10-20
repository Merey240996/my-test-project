<?php
declare(strict_types=1);
namespace App\Api;

use IO\Constants as EIO;
use IO\Api\CrudApi;
use IO\Api\ApiResult;
use App\Classes\Lead;
use App\Classes\LeadStage;
use App\Classes\Deal;
use App\Classes\DealStage;
use App\Classes\Client;
use App\Classes\UserRole;
use App\Classes\AuditLog;

class LeadApi extends CrudApi
{
	public static function struct(): array
	{
		return [
			'class' => Lead::class,
			'fields' => [
				'pkid' => ['type' => 'int'],
				'company_pkid' => ['type' => 'int'],
				'client_pkid' => ['type' => 'int', 'label' => 'Контакт'],
				'funnel_pkid' => ['type' => 'int', 'label' => 'Воронка'],
				'lead_stage_pkid' => ['type' => 'int', 'label' => 'Этап'],
				'responsible_user' => ['type' => 'int', 'label' => 'Ответственный'],
				'name' => ['type' => 'string', 'label' => 'Название'],
				'description' => ['type' => 'string', 'label' => 'Описание'],
				'potentialAmount' => ['type' => 'float', 'label' => 'Потенциальная сумма'],
				'source' => ['type' => 'int', 'label' => 'Источник'],
				'status' => ['type' => 'int', 'label' => 'Статус'],
				'refusalReason' => ['type' => 'string', 'label' => 'Причина отказа'],
				'convertedDealPkid' => ['type' => 'int', 'label' => 'Сделка'],
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
		$rules['static']['actionAdvance'] = 1;
		$rules['static']['actionRollback'] = 1;
		$rules['static']['actionCanRollback'] = 1;
		$rules['static']['actionReject'] = 1;
		$rules['static']['actionStats'] = 1;
		$rules['static']['actionFilterValues'] = 1;
		$rules['static']['actionCanDelete'] = 1;
		
		return $rules;
	}

	// Статусные константы вынесены в App\Enums\EnumApi (LEAD_STATUS_TRANSITIONS,
	// LEAD_STATUS_ROLLBACK, LEAD_REJECTABLE_STATUSES, LEAD_DELETE_ROLE_PKIDS) —
	// это единственный источник правды, фронт получает тот же набор через
	// crm.api.lead.enums (см. doEnums ниже).

	// Всегда скрываем удалённые
	public function onSearchFilter(array $filter): array
	{
		$filter['is_deleted'] = 0;
		return $filter;
	}

	// ВАЖНО: 'join' здесь больше НЕ используем — IO\SqlClass::search() ломается
	// на joinRaw() для Eloquent-моделей (баг ядра, правки нет доступа).
	// Название этапа (stage_name) теперь подтягивается отдельным запросом
	// в doSearch() ниже, по аналогии с тем, как ClientApi подмешивает
	// связанные данные (ClientFieldValues) отдельными запросами, а не JOIN'ом.

	// Заполняем timestamp-поля при создании/сохранении
	public function onUpdateData(array $save_data, array $update_data): array
	{
		if (empty($this->item_pkid)) {
			// создание новой записи
			$update_data['gmtimeAdd'] = gmdate('Y-m-d H:i:s');
			$update_data['ugmtimeAdd'] = time();
			$update_data['is_deleted'] = 0;
			$update_data['status'] = $update_data['status'] ?? 0;

			// Название обязательно должно попасть в саму запись — раньше
			// пустое название подставлялось только на экране (страница
			// "Лиды"), а в БД писалась пустая строка. pkid на этом этапе
			// ещё не присвоен (запись не вставлена), поэтому генерируем
			// название по времени создания, а не по id.
			if (trim((string) ($update_data['name'] ?? '')) === '') {
				$update_data['name'] = 'Лид от ' . gmdate('d.m.Y H:i');
			}
		}

		// Ответственный обязателен как при создании, так и при сохранении
		$responsible = $update_data['responsible_user'] ?? ($save_data['responsible_user'] ?? null);
		if (empty($responsible)) {
			throw new \RuntimeException('Не указан ответственный');
		}

		if (array_key_exists('status', $update_data) && (int) $update_data['status'] !== 3) {
			$update_data['refusalReason'] = null;
		}

		$update_data['gmtimeChange'] = gmdate('Y-m-d H:i:s');
		$update_data['ugmtimeChange'] = time();

		return $update_data;
	}

	
	public function doCreate(ApiResult $result, array $params): ApiResult
	{
		$item = $params['item'] ?? [];

		// Если ответственный выбран вручную — сохраняем его.
		if (empty($item['responsible_user'])) {
			$companyPkid = (int) ($item['company_pkid'] ?? 0);

			if ($companyPkid <= 0) {
				return $result->showResult(
					[],
					'Не указана компания для распределения лида',
					EIO::VALIDATION
				);
			}

			try {
				$item['responsible_user'] = $this->getNextLeadResponsible($companyPkid);
			} catch (\Throwable $e) {
				return $result->showResult(
					[],
					$e->getMessage(),
					EIO::VALIDATION
				);
			}

			$params['item'] = $item;
			
		}

		$res = parent::doCreate($result, $params);

		if ($res->error_code === EIO::OK) {
			try {
				$createdItem = $res->toArray();
				$pkid = (int) xarr($createdItem, 'pkid', 0);
				$userPkid = (int) $this->app()->session->getUserID();
				$companyPkid = (int) xarr($createdItem, 'company_pkid', 1);

				AuditLog::record(
					$companyPkid,
					$userPkid,
					AuditLog::ENTITY_LEAD,
					$pkid,
					AuditLog::ACTION_CREATE,
					null,
					$createdItem
				);
			} catch (\Throwable $e) {
				// Ошибка аудита не должна отменять создание лида.
			}
		}

		return $res;
	}

	
	private function getNextLeadResponsible(int $companyPkid): int
	{
		$managerIds = UserRole::where('company_pkid', $companyPkid)
			->where('role_pkid', 3)
			->where('is_deleted', 0)
			->orderBy('user_pkid', 'asc')
			->pluck('user_pkid')
			->map(static fn ($id) => (int) $id)
			->unique()
			->values()
			->all();

		if (empty($managerIds)) {
			throw new \RuntimeException(
				'В компании нет активных менеджеров для распределения лидов'
			);
		}

		$state = \IO\Database\DB::table('lead_distribution_state')
			->where('company_pkid', $companyPkid)
			->first();

		$lastUserPkid = (int) ($state->last_user_pkid ?? 0);
		$nextIndex = 0;

		foreach ($managerIds as $index => $managerId) {
			if ($managerId === $lastUserPkid) {
				$nextIndex = ($index + 1) % count($managerIds);
				break;
			}
		}

		$nextUserPkid = $managerIds[$nextIndex];
		$now = gmdate('Y-m-d H:i:s');
		$nowInt = time();

		if ($state) {
			\IO\Database\DB::table('lead_distribution_state')
				->where('company_pkid', $companyPkid)
				->update([
					'last_user_pkid' => $nextUserPkid,
					'gmtimeChange' => $now,
					'ugmtimeChange' => $nowInt,
				]);
		} else {
			\IO\Database\DB::table('lead_distribution_state')
				->insert([
					'company_pkid' => $companyPkid,
					'last_user_pkid' => $nextUserPkid,
					'gmtimeChange' => $now,
					'ugmtimeChange' => $nowInt,
				]);
		}

		return $nextUserPkid;
	}

	// Глобальный текстовый поиск + подмешивание stage_name без JOIN.
	// TODO: когда будет доступна модель клиента (crm_clients), добавить сюда
	// поиск по имени/телефону/email контакта — сейчас ищем только по source.
	public function doSearch(ApiResult $result, array $params): ApiResult
	{
		try {
			$search = trim((string) xarr($params, 'search', ''));

			// Разделяем скалярные фильтры и объекты {op, values}
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

			// Базовые параметры поиска
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

			// Применяем колоночные фильтры
			$search_filter = $this->applyColumnFilters($search_filter, $columnFilterObjects);

			// === ГЛОБАЛЬНЫЙ ПОИСК — добавляем в $search_filter ===
			if ($search !== '') {
				// Ищем клиентов
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
					['%source' => $search],
				];

				if (!empty($clientPkids)) {
					$orConditions[] = ['client_pkid' => $clientPkids];
				}

				// Добавляем в $search_filter, а не в $params['filter']
				$search_filter['$and'][] = ['$or' => $orConditions];
			}

			$search_params['filter'] = $search_filter;

			$arr = Lead::search($search_params);
			$items = $arr['res'] ?? [];
			$total = $arr['count'] ?? 0;

			// Подмешиваем stage_name
			if (!empty($items)) {
				$stagePkids = array_values(array_unique(array_filter(
					array_map(fn($row) => (int) ($row['lead_stage_pkid'] ?? 0), $items)
				)));

				if (!empty($stagePkids)) {
					$stages = LeadStage::whereIn('pkid', $stagePkids)
						->where('is_deleted', 0)
						->get(['pkid', 'name']);

					$stageNames = [];
					foreach ($stages as $stage) {
						$stageNames[(int) $stage->pkid] = $stage->name;
					}

					foreach ($items as &$row) {
						$pkid = (int) ($row['lead_stage_pkid'] ?? 0);
						$row['stage_name'] = $stageNames[$pkid] ?? null;
					}
					unset($row);
				}
			}

			return $result->showSuccess([
				'items' => $items,
				'total' => $total,
			]);
		} catch (\Throwable $e) {
			return $result->showResult([], $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), EIO::VALIDATION);
		}
	}

	// Перетаскивание карточки лида между колонками канбана (смена этапа)
	public function doMove(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$lead_stage_pkid = (int) xarr($params, 'lead_stage_pkid', 0);

		if ($pkid <= 0) {
			return $result->showResult([], 'pkid не передан', EIO::VALIDATION);
		}

		if ($lead_stage_pkid <= 0) {
			return $result->showResult([], 'lead_stage_pkid не передан', EIO::VALIDATION);
		}

		$lead = Lead::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);

		if (!$lead) {
			return $result->showResult([], 'Лид не найден', EIO::NOT_FOUND);
		}

		$stage = LeadStage::findItem([
			'filter' => ['pkid' => $lead_stage_pkid, 'is_deleted' => 0],
		]);

		if (!$stage) {
			return $result->showResult([], 'Этап не найден', EIO::VALIDATION);
		}

		$ok = Lead::updateByFilter(
			['pkid' => $pkid],
			[
				'lead_stage_pkid' => $lead_stage_pkid,
				'gmtimeChange' => gmdate('Y-m-d H:i:s'),
				'ugmtimeChange' => time(),
			]
		);

		if ($ok !== 1) {
			return $result->showResult([], 'Не удалось переместить лид', EIO::ERROR);
		}

		try {
			$userPkid = (int) $this->app()->session->getUserID();
			AuditLog::record(
				(int) $lead['company_pkid'],
				$userPkid,
				AuditLog::ENTITY_LEAD,
				$pkid,
				AuditLog::ACTION_STAGE_CHANGE,
				['lead_stage_pkid' => $lead['lead_stage_pkid']],
				['lead_stage_pkid' => $lead_stage_pkid]
			);
		} catch (\Throwable $e) {
			// ignore error
		}

		return $result->showSuccess([
			'pkid' => $pkid,
			'lead_stage_pkid' => $lead_stage_pkid,
		]);
	}

	// Продвижение лида на следующий статус (Подтвердить / Квалифицировать)
	public function doAdvance(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);

		if ($pkid <= 0) {
			return $result->showResult([], 'pkid не передан', EIO::VALIDATION);
		}

		$lead = Lead::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);

		if (!$lead) {
			return $result->showResult([], 'Лид не найден', EIO::NOT_FOUND);
		}

		$currentStatus = (int) $lead['status'];

		if (!isset(EnumApi::LEAD_STATUS_TRANSITIONS[$currentStatus])) {
			return $result->showResult([], 'Для текущего статуса лида действие недоступно', EIO::VALIDATION);
		}

		$nextStatus = EnumApi::LEAD_STATUS_TRANSITIONS[$currentStatus];

		// Статус 2 = "Конвертирован" — это не просто смена статуса, это
		// реальная конвертация лида в сделку. Раньше здесь только менялся
		// $lead['status'], а Deal никогда не создавался: фронт показывал
		// "передан в воронку сделок", но в разделе "Сделки" ничего не
		// появлялось, и поле convertedDealPkid оставалось пустым.
		$dealPkid = null;

		if ($nextStatus === 2) {
			$conversion = $this->convertLeadToDeal($lead, $pkid);
			if (!$conversion['ok']) {
				return $result->showResult([], $conversion['error'], EIO::VALIDATION);
			}
			$dealPkid = $conversion['dealPkid'];
		}

		$updateData = [
			'status' => $nextStatus,
			'gmtimeChange' => gmdate('Y-m-d H:i:s'),
			'ugmtimeChange' => time(),
		];

		if ($dealPkid !== null) {
			$updateData['convertedDealPkid'] = $dealPkid;
		}

		$ok = Lead::updateByFilter(['pkid' => $pkid], $updateData);

		if ($ok !== 1) {
			return $result->showResult([], 'Не удалось обновить статус лида', EIO::ERROR);
		}

		try {
			$userPkid = (int) $this->app()->session->getUserID();
			AuditLog::record(
				(int) $lead['company_pkid'],
				$userPkid,
				AuditLog::ENTITY_LEAD,
				$pkid,
				AuditLog::ACTION_STAGE_CHANGE,
				['status' => $currentStatus],
				['status' => $nextStatus, 'convertedDealPkid' => $dealPkid]
			);

			if ($dealPkid !== null) {
				AuditLog::record(
					(int) $lead['company_pkid'],
					$userPkid,
					AuditLog::ENTITY_DEAL,
					$dealPkid,
					AuditLog::ACTION_ADD_CLIENT_TO_BASE,
					null,
					['deal_pkid' => $dealPkid, 'client_pkid' => $lead['client_pkid'], 'lead_pkid' => $pkid]
				);
			}
		} catch (\Throwable $e) {
			// ignore error
		}

		return $result->showSuccess([
			'pkid' => $pkid,
			'status' => $nextStatus,
			'convertedDealPkid' => $dealPkid,
		]);
	}

	/**
	 * Создаёт сделку из лида (или возвращает уже существующую, если лид
	 * конвертировался раньше) — общая логика для doAdvance и для обычного
	 * редактирования лида через doSave, когда статус меняют на
	 * "Конвертирован" (2) напрямую через форму, а не кнопкой "Продвинуть".
	 * Без этого выставление статуса 2 через модалку редактирования просто
	 * меняло бы status, а сделка в разделе "Сделки" не появлялась бы.
	 *
	 * @return array{ok: bool, dealPkid: ?int, error: ?string}
	 */
	private function convertLeadToDeal(array $lead, int $leadPkid): array
	{
		if (!empty($lead['convertedDealPkid'])) {
			// Лид уже был конвертирован ранее (повторный вызов/гонка) —
			// не плодим вторую сделку.
			return ['ok' => true, 'dealPkid' => (int) $lead['convertedDealPkid'], 'error' => null];
		}

		$funnelPkid = (int) $lead['funnel_pkid'];

		// Первый этап сделки в той же воронке, куда переходит лид.
		$firstStage = DealStage::where('funnel_pkid', $funnelPkid)
			->where('is_deleted', 0)
			->orderBy('sortOrder', 'asc')
			->first();

		if (!$firstStage) {
			return [
				'ok' => false,
				'dealPkid' => null,
				'error' => 'Для воронки лида не настроены этапы сделок — сначала добавьте хотя бы один этап сделки в этой воронке',
			];
		}

		$client = Client::where('pkid', (int) $lead['client_pkid'])
			->where('is_deleted', 0)
			->first();

		$deal = new Deal();
		$deal->company_pkid = (int) $lead['company_pkid'];
		$deal->name = $lead['name'];
		$deal->description = $lead['description'] ?? null;
		$deal->client_pkid = (int) $lead['client_pkid'];
		$deal->lead_pkid = $leadPkid;
		$deal->funnel_pkid = $funnelPkid;
		$deal->stage_pkid = (int) $firstStage->pkid;
		$deal->responsible_user = (int) $lead['responsible_user'];
		$deal->contactPerson = $client->company ?? null;
		$deal->contactEmail = $client->email ?? null;
		$deal->contactPhone = $client->phone ?? null;
		$deal->amount = (float) ($lead['potentialAmount'] ?? 0);
		$deal->status = 0; // В работе
		$deal->is_deleted = 0;
		$deal->gmtimeAdd = gmdate('Y-m-d H:i:s');
		$deal->gmtimeChange = gmdate('Y-m-d H:i:s');
		$deal->ugmtimeAdd = time();
		$deal->ugmtimeChange = time();
		$deal->save();

		return ['ok' => true, 'dealPkid' => (int) $deal->pkid, 'error' => null];
	}

	/**
	 * Обычное редактирование лида (модалка LeadModal) тоже умеет менять
	 * status — это тот же самый рычаг, что и doRollback, просто с другой
	 * стороны формы. Без этой проверки правило "откат — только
	 * администратору" из doRollback ничего не стоило бы: любой пользователь
	 * мог бы просто открыть лид на редактирование и выставить статус на
	 * шаг назад через обычный Save.
	 */
	public function doSave(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$oldLead = $pkid > 0 ? Lead::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]) : null;

		if ($pkid > 0) {
			$lead = $oldLead;

			if ($lead) {
				$item = xarr($params, 'item', []);

				if (array_key_exists('status', $item)) {
					$currentStatus = (int) $lead['status'];
					$newStatus = (int) $item['status'];

					if ($this->isStatusRollback($currentStatus, $newStatus)
						&& !$this->userCanRollbackLeadStatus((int) $lead['company_pkid'])
					) {
						return $result->showResult([], 'Откат статуса доступен только администратору', EIO::DENY);
					}

					if ($newStatus === 2 && $currentStatus !== 2) {
						$effectiveLead = array_merge($lead, $item);

						$conversion = $this->convertLeadToDeal($effectiveLead, $pkid);
						if (!$conversion['ok']) {
							return $result->showResult([], $conversion['error'], EIO::VALIDATION);
						}

						$item['convertedDealPkid'] = $conversion['dealPkid'];
						$params['item'] = $item;

						try {
							$userPkid = (int) $this->app()->session->getUserID();
							AuditLog::record(
								(int) $lead['company_pkid'],
								$userPkid,
								AuditLog::ENTITY_DEAL,
								(int) $conversion['dealPkid'],
								AuditLog::ACTION_ADD_CLIENT_TO_BASE,
								null,
								['deal_pkid' => $conversion['dealPkid'], 'client_pkid' => $lead['client_pkid'], 'lead_pkid' => $pkid]
							);
						} catch (\Throwable $e) {
							// ignore error
						}
					}
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
				AuditLog::record($companyPkid, $userPkid, AuditLog::ENTITY_LEAD, $savedPkid, $action, $oldLead, $item);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	// Переход считается "откатом", если он в точности совпадает с обратным
	// переходом из STATUS_ROLLBACK — той же карты, которой руководствуется
	// doRollback. Так оба пути изменения статуса (диалог редактирования и
	// прямой вызов rollback) подчиняются одному и тому же правилу.
	private function isStatusRollback(int $from, int $to): bool
	{
		return $from !== $to && isset(EnumApi::LEAD_STATUS_ROLLBACK[$from]) && EnumApi::LEAD_STATUS_ROLLBACK[$from] === $to;
	}

	/**
	 * Откат статуса лида на шаг назад. Доступно только пользователям с ролью
	 * "Администратор" (role_pkid = ADMIN_ROLE_PKID) — это намеренно
	 * привилегированное действие, а не часть обычной работы менеджера с
	 * воронкой, поэтому продвижение вперёд (doAdvance) доступно всем, а
	 * откат назад — только администратору.
	 */
	public function doRollback(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);

		if ($pkid <= 0) {
			return $result->showResult([], 'pkid не передан', EIO::VALIDATION);
		}

		$lead = Lead::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);

		if (!$lead) {
			return $result->showResult([], 'Лид не найден', EIO::NOT_FOUND);
		}

		if (!$this->userCanRollbackLeadStatus((int) $lead['company_pkid'])) {
			return $result->showResult([], 'Откат статуса доступен только администратору', EIO::VALIDATION);
		}

		$currentStatus = (int) $lead['status'];

		if (!isset(EnumApi::LEAD_STATUS_ROLLBACK[$currentStatus])) {
			return $result->showResult([], 'Для текущего статуса лида откат недоступен', EIO::VALIDATION);
		}

		$prevStatus = EnumApi::LEAD_STATUS_ROLLBACK[$currentStatus];

		$updateData = [
			'status' => $prevStatus,
			'gmtimeChange' => gmdate('Y-m-d H:i:s'),
			'ugmtimeChange' => time(),
		];

		// Возврат из "Отклонён" — причина отказа больше не актуальна.
		if ($currentStatus === 3) {
			$updateData['refusalReason'] = '';
		}

		// Важно: convertedDealPkid НЕ трогаем при откате из "Конвертирован".
		// Сделка уже создана и продолжает жить своей жизнью — если лид потом
		// снова доведут до конвертации, doAdvance увидит существующий
		// convertedDealPkid и не создаст вторую сделку-дубликат.

		$ok = Lead::updateByFilter(['pkid' => $pkid], $updateData);

		if ($ok !== 1) {
			return $result->showResult([], 'Не удалось откатить статус лида', EIO::ERROR);
		}

		try {
			$userPkid = (int) $this->app()->session->getUserID();
			AuditLog::record(
				(int) $lead['company_pkid'],
				$userPkid,
				AuditLog::ENTITY_LEAD,
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
	// статуса лида назад (см. userCanRollbackLeadStatus).
	private function userCanRollbackLeadStatus(int $company_pkid): bool
	{
		$role_pkids = $this->getCurrentUserRolePkids($company_pkid);

		return in_array(EnumApi::ADMIN_ROLE_PKID, $role_pkids, true);
	}

	// Лёгкий эндпоинт для фронта — показывать ли кнопку "Откатить статус".
	// Бэкенд всё равно перепроверяет право в doRollback, это только для UI.
	public function doCanRollback(ApiResult $result, array $params): ApiResult
	{
		$company_pkid = (int) xarr($params, 'company_pkid', 0);

		return $result->showSuccess([
			'canRollback' => $this->userCanRollbackLeadStatus($company_pkid),
		]);
	}

	// Отклонение лида (с обязательной причиной отказа)
	public function doReject(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$reason = trim((string) xarr($params, 'refusalReason', ''));

		if ($pkid <= 0) {
			return $result->showResult([], 'pkid не передан', EIO::VALIDATION);
		}

		if ($reason === '') {
			return $result->showResult([], 'Не указана причина отказа', EIO::VALIDATION);
		}

		$lead = Lead::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);

		if (!$lead) {
			return $result->showResult([], 'Лид не найден', EIO::NOT_FOUND);
		}

		$currentStatus = (int) $lead['status'];

		if (!in_array($currentStatus, EnumApi::LEAD_REJECTABLE_STATUSES, true)) {
			return $result->showResult([], 'Лид в текущем статусе нельзя отклонить', EIO::VALIDATION);
		}

		$ok = Lead::updateByFilter(
			['pkid' => $pkid],
			[
				'status' => 3,
				'refusalReason' => $reason,
				'gmtimeChange' => gmdate('Y-m-d H:i:s'),
				'ugmtimeChange' => time(),
			]
		);

		if ($ok !== 1) {
			return $result->showResult([], 'Не удалось отклонить лид', EIO::ERROR);
		}

		try {
			$userPkid = (int) $this->app()->session->getUserID();
			AuditLog::record(
				(int) $lead['company_pkid'],
				$userPkid,
				AuditLog::ENTITY_LEAD,
				$pkid,
				AuditLog::ACTION_STAGE_CHANGE,
				['status' => $currentStatus],
				['status' => 3, 'refusalReason' => $reason]
			);
		} catch (\Throwable $e) {
			// ignore error
		}

		return $result->showSuccess([
			'pkid' => $pkid,
			'status' => 3,
			'refusalReason' => $reason,
		]);
	}

	public function doStats(ApiResult $result, array $params): ApiResult
	{
		$companyPkid = (int) xarr($params, 'company_pkid', 0);
		$funnelPkid  = (int) xarr($params, 'funnel_pkid', 0);

		$base = Lead::where('company_pkid', $companyPkid)
			->where('funnel_pkid', $funnelPkid)
			->where('is_deleted', 0);

		// Квалифицированные (status = 1)
		$qualified = (clone $base)->where('status', 1);
		$qualifiedCount = $qualified->count();
		$qualifiedSum   = $qualified->sum('potentialAmount');

		// В работе (status = 0)
		$inProgress = (clone $base)->where('status', 0);
		$inProgressCount = $inProgress->count();
		$inProgressSum   = $inProgress->sum('potentialAmount');

		// Отклонённые (status = 3)
		$rejected = (clone $base)->where('status', 3);
		$rejectedCount = $rejected->count();
		$rejectedSum   = $rejected->sum('potentialAmount');

		// Топ источник
		$topSource = (clone $base)
			->selectRaw('source, COUNT(*) as cnt')
			->groupBy('source')
			->orderByDesc('cnt')
			->first();

		$totalCount = (clone $base)->count();
		$totalSum   = (clone $base)->sum('potentialAmount');

		// Топ причина отказа (по источнику среди отклонённых)
		$topStall = (clone $base)->where('status', 3)
			->selectRaw('source, COUNT(*) as cnt')
			->groupBy('source')
			->orderByDesc('cnt')
			->first();

		// NEW: распределение по источникам (для currentSourceStats на фронте)
		$sourceDistributionRows = (clone $base)
			->selectRaw('source, COUNT(*) as cnt')
			->groupBy('source')
			->get();
		$sourceDistribution = [];
		foreach ($sourceDistributionRows as $row) {
			$sourceDistribution[(int) $row->source] = (int) $row->cnt;
		}

		// NEW: распределение по этапам воронки (для currentFunnelStages)
		$funnelStagesRows = (clone $base)
			->selectRaw('lead_stage_pkid, COUNT(*) as cnt')
			->groupBy('lead_stage_pkid')
			->get();
		$funnelStages = [];
		foreach ($funnelStagesRows as $row) {
			$funnelStages[(int) $row->lead_stage_pkid] = (int) $row->cnt;
		}

		// NEW: сумма potentialAmount по каждому статусу (для leadStructure)
		$byStatusSumRows = (clone $base)
			->selectRaw('status, SUM(potentialAmount) as total')
			->groupBy('status')
			->get();
		$byStatusSum = [];
		foreach ($byStatusSumRows as $row) {
			$byStatusSum[(int) $row->status] = (float) $row->total;
		}

		// NEW: средний чек по всей воронке (для avgLead)
		$avgAmount = $totalCount ? round($totalSum / $totalCount) : 0;

		// NEW: динамика по месяцам за последние 6 месяцев (для графика)
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
			'qualified' => ['count' => $qualifiedCount, 'sum' => $qualifiedSum],
			'inProgress' => ['count' => $inProgressCount, 'sum' => $inProgressSum],
			'rejected' => ['count' => $rejectedCount, 'sum' => $rejectedSum],
			'topSource' => $topSource ? [
				'source' => $topSource->source,
				'count' => $topSource->cnt,
				'pct' => $totalCount ? round($topSource->cnt / $totalCount * 100) : 0
			] : null,
			'topStall' => $topStall ? [
				'source' => $topStall->source,
				'count' => $topStall->cnt,
				'pct' => $rejectedCount ? round($topStall->cnt / $rejectedCount * 100) : 0
			] : null,
			'totalCount' => $totalCount,
			// NEW
			'totalSum' => $totalSum,
			'sourceDistribution' => $sourceDistribution,
			'funnelStages' => $funnelStages,
			'byStatusSum' => $byStatusSum,
			'avgAmount' => $avgAmount,
			'lost' => ['count' => $rejectedCount, 'sum' => $rejectedSum], // алиас rejected — под именем, которое ждёт фронт
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

		$base = Lead::where('company_pkid', $companyPkid)
			->where('funnel_pkid', $funnelPkid)
			->where('is_deleted', 0);

		// Уникальные значения source
		$sources = (clone $base)
			->select('source')
			->whereNotNull('source')
			->distinct()
			->pluck('source')
			->map(fn($v) => (int) $v)
			->values()
			->toArray();

		// Уникальные значения status
		$statuses = (clone $base)
			->select('status')
			->distinct()
			->pluck('status')
			->map(fn($v) => (int) $v)
			->values()
			->toArray();

		// Уникальные значения responsible_user
		$managers = (clone $base)
			->select('responsible_user')
			->whereNotNull('responsible_user')
			->distinct()
			->pluck('responsible_user')
			->values()
			->toArray();

		return $result->showSuccess([
			'sources'  => $sources,
			'statuses' => $statuses,
			'managers' => $managers,
		]);
	}

	private function applyColumnFilters(array $search_filter, array $columnFilterObjects): array
	{
		// Маппинг фронтовых имён на реальные колонки БД
		$fieldMap = [
			'manager' => 'responsible_user',
			'source'  => 'source',
			'status'  => 'status',
		];

		foreach ($columnFilterObjects as $key => $cond) {
			$field = $fieldMap[$key] ?? $key;  // ← замена manager → responsible_user
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
					$search_filter['$and'][] = ['$or' => [
						['=' . $field => null],
						['=' . $field => ''],
					]];
					break;

				case 'notEmpty':
					$search_filter['$and'][] = ['!=' . $field => null];
					$search_filter['$and'][] = ['!=' . $field => ''];
					break;

				default:
					break;
			}
		}

		return $search_filter;
	}

	public function doCanDelete(ApiResult $result, array $params): ApiResult
	{
		$company_pkid = (int) xarr($params, 'company_pkid', 0);
		return $result->showSuccess([
			'canDelete' => $this->userCanDeleteLead($company_pkid),
		]);
	}

	// Удаление лида доступно только администратору (pkid 1) и РОП (pkid 2) —
	// см. DELETE_ROLE_PKIDS/userCanDeleteLead. Бэкенд перепроверяет право
	// здесь независимо от doCanDelete, который отдаёт флаг только для UI.
	public function doDelete(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		$oldModel = $pkid > 0 ? Lead::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]) : null;

		if ($pkid > 0) {
			$lead = Lead::findItem(['filter' => ['pkid' => $pkid, 'is_deleted' => 0]]);

			if ($lead) {
				$company_pkid = (int) $lead['company_pkid'];

				if (!$this->userCanDeleteLead($company_pkid)) {
					return $result->showResult([], 'Нет прав на удаление лидов', EIO::DENY);
				}
			}
		}

		$res = parent::doDelete($result, $params);
		if ($res->error_code === EIO::OK && $oldModel) {
			try {
				$userPkid = (int) $this->app()->session->getUserID();
				$companyPkid = (int) xarr($oldModel, 'company_pkid', 1);

				AuditLog::record($companyPkid, $userPkid, AuditLog::ENTITY_LEAD, $pkid, AuditLog::ACTION_DELETE, $oldModel, null);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	private function userCanDeleteLead(int $company_pkid): bool
	{
		$role_pkids = $this->getCurrentUserRolePkids($company_pkid);
		return !empty(array_intersect(EnumApi::LEAD_DELETE_ROLE_PKIDS, $role_pkids));
	}

}
