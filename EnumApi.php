<?php
declare(strict_types=1);
namespace App\Api;

use IO\Api\CoreApi;
use IO\Api\ApiResult;

/**
 * Единый реестр "числовых enum"-констант проекта: статусы, разрешённые
 * переходы/откаты между ними, роли и т.п.
 *
 * Раньше эти списки жили как `private const` прямо в *Api-классах
 * (LeadApi, DealApi, OrderApi...) и вручную дублировались во Vue-компонентах
 * (см. историю LeadModal.vue / OrderModal.vue — комментарии "Зеркало X::Y").
 * Теперь это единственный источник правды: другие *Api-классы бэкенда
 * используют константы отсюда напрямую (EnumApi::LEAD_STATUS_ROLLBACK и т.д.),
 * а фронт получает их через сам этот класс — он не CRUD, а обычный
 * CoreApi-эндпоинт (без таблицы), резолвится в crm.api.enum.*.
 *
 * Один список — один action (а не общий "отдай всё"), чтобы каждый
 * компонент на фронте тянул только то, что ему реально нужно:
 *
 *   crm.api.enum.leadStatusTransitions
 *   crm.api.enum.leadStatusRollback
 *   crm.api.enum.leadRejectableStatuses
 *   crm.api.enum.leadDeleteRolePkids
 *   crm.api.enum.leadStatusLabels
 *   crm.api.enum.leadSourceLabels
 *   crm.api.enum.dealStatusRollback
 *   crm.api.enum.dealDeleteRolePkids
 *   crm.api.enum.dealStatusLabels
 *   crm.api.enum.orderStatusTransitions
 *   crm.api.enum.orderStatusRollback
 *   crm.api.enum.orderCancellableStatuses
 *   crm.api.enum.orderDeleteRolePkids
 *   crm.api.enum.orderLockedStatuses
 *   crm.api.enum.orderStatusLabels
 *   crm.api.enum.invoiceStatusLabels
 *   crm.api.enum.entityTypes
 *   crm.api.enum.adminRolePkid
 *
 * Пример на фронте (см. MfModel.js):
 *   const { statusRollback } = await mfModel.getLeadStatusRollback();
 */
class EnumApi extends CoreApi
{
	public static function rules(): array
	{
		return [
			'static' => [
				'actionLeadStatusTransitions' => 1,
				'actionLeadStatusRollback' => 1,
				'actionLeadRejectableStatuses' => 1,
				'actionLeadDeleteRolePkids' => 1,
				'actionLeadStatusLabels' => 1,
				'actionLeadSourceLabels' => 1,
				'actionDealStatusRollback' => 1,
				'actionDealDeleteRolePkids' => 1,
				'actionDealStatusLabels' => 1,
				'actionOrderStatusTransitions' => 1,
				'actionOrderStatusRollback' => 1,
				'actionOrderCancellableStatuses' => 1,
				'actionOrderDeleteRolePkids' => 1,
				'actionOrderLockedStatuses' => 1,
				'actionOrderStatusLabels' => 1,
				'actionInvoiceStatusLabels' => 1,
				'actionOfferTypeLabels' => 1,
				'actionEntityTypes' => 1,
				'actionAdminRolePkid' => 1,
			],
			'object' => [],
		];
	}

	// ---- Lead ----

	// Разрешённые переходы статуса: откуда → куда (для "продвижения" по воронке)
	// 0 Новый → 1 В работе (подтверждение) → 2 Конвертирован (квалификация)
	public const LEAD_STATUS_TRANSITIONS = [
		0 => 1,
		1 => 2,
	];

	// Откат статуса назад (только для администратора) — зеркало LEAD_STATUS_TRANSITIONS.
	// Статус 3 (Отклонён) — особый случай: мы не храним, из какого статуса лид
	// был отклонён, поэтому откатываем его всегда в "Новый".
	public const LEAD_STATUS_ROLLBACK = [
		1 => 0,
		2 => 1,
		3 => 0,
	];

	// Из каких статусов лид можно отклонить
	public const LEAD_REJECTABLE_STATUSES = [0, 1];

	public const LEAD_DELETE_ROLE_PKIDS = [1, 2];

	// Человекочитаемые подписи статусов лида (для таблиц/бейджей/фильтров).
	// Раньше был захардкожен как STATUS_LABELS прямо в Leads.vue.
	public const LEAD_STATUS_LABELS = [
		0 => 'Новый',
		1 => 'В работе',
		2 => 'Конвертирован',
		3 => 'Отклонён',
	];

	// Подписи источников лида. Раньше был захардкожен как SOURCE_LABELS
	// прямо в Leads.vue.
	public const LEAD_SOURCE_LABELS = [
		1 => 'Сайт',
		2 => 'Ярмарка',
		3 => 'Регистрация',
		4 => 'Богин',
	];

	// ---- Deal ----

	// Откат статуса сделки назад: Проиграна/Конвертирована в заказ → Открыта.
	// Раньше жил как private const STATUS_ROLLBACK в DealApi.
	public const DEAL_STATUS_ROLLBACK = [
		2 => 0,
		3 => 0,
	];

	// Раньше private const DELETE_ROLE_PKIDS в DealApi (зеркало LEAD_DELETE_ROLE_PKIDS).
	public const DEAL_DELETE_ROLE_PKIDS = [1, 2];

	// Раньше захардкожен как STATUS_LABELS прямо в Deals.vue.
	public const DEAL_STATUS_LABELS = [
		0 => 'В работе',
		2 => 'Проиграна',
		3 => 'Конвертирована в заказ',
	];

	// ---- Order ----

	// Разрешённые переходы статуса вперёд по воронке доставки/оплаты.
	// 0 Новый → 1 В доставке → 2 Оплачен. Раньше private const
	// STATUS_TRANSITIONS в OrderApi.
	public const ORDER_STATUS_TRANSITIONS = [
		0 => 1,
		1 => 2,
	];

	// Откат статуса назад (только для администратора) — зеркало
	// ORDER_STATUS_TRANSITIONS. Статус 3 (Отменён) — не храним, из какого
	// статуса заказ был отменён, поэтому откатываем его всегда в "Новый".
	// Раньше private const STATUS_ROLLBACK в OrderApi.
	public const ORDER_STATUS_ROLLBACK = [
		1 => 0,
		2 => 1,
		3 => 0,
	];

	// Из каких статусов заказ можно отменить. Раньше private const
	// CANCELLABLE_STATUSES в OrderApi.
	public const ORDER_CANCELLABLE_STATUSES = [0, 1];

	// Раньше private const DELETE_ROLE_PKIDS в OrderApi.
	public const ORDER_DELETE_ROLE_PKIDS = [1, 2];

	// Финальные статусы заказа, после которых редактирование в форме
	// блокируется (2 = Оплачен, 3 = Отменён). Раньше захардкожен как
	// LOCKED_STATUSES прямо в OrderModal.vue. Не совпадает с ключами
	// ORDER_STATUS_ROLLBACK (там ещё есть 1 = В доставке — из него есть
	// откат, но форма не блокируется), поэтому это отдельный список,
	// а не производная от rollback.
	public const ORDER_LOCKED_STATUSES = [2, 3];

	// Раньше захардкожен как STATUS_LABELS прямо в Orders.vue.
	public const ORDER_STATUS_LABELS = [
		0 => 'Новый',
		1 => 'В доставке',
		2 => 'Оплачен',
		3 => 'Отменён',
	];

	// ---- Invoice ----

	// Статусы инвойса — не наши, а зеркало $ioEnum['invoices_statuses'] из
	// сервиса trade (там же живёт и сама сущность invoice, см. комментарии
	// в InvoiceApi.php — invoice не имеет своей таблицы в CRM). Коды и
	// порядок 1:1 скопированы из trade-конфига enum'а (те самые
	// 'trade.invoices.statuses.N' переводы), а не придуманы нами — если
	// в trade поменяют коды/добавят статус, надо поправить и здесь.
	// -1 — отмена, 0..12 — прямой путь заказа, 101/102 — путь возврата
	// (type=1, отдельная ветка статусов, в обычном UI заказа не показывается).
	public const INVOICE_STATUS_LABELS = [
		-1  => 'Отменён',
		0   => 'Заказан клиентом',
		1   => 'Принят',
		2   => 'Подготавливается',
		3   => 'Заказан у поставщика',
		4   => 'Ожидает отправки в регион',
		5   => 'Транзит',
		6   => 'Комплектуется',
		7   => 'Доступен',
		8   => 'Готов к отправке',
		9   => 'В резерве (Курьер)',
		10  => 'Посылка сформирована',
		11  => 'На доставке',
		12  => 'Доставлен',
		101 => 'Возврат создан',
		102 => 'Возврат выполнен',
	];

	// Тип предложения (цены), по которому подбирается offer товара при
	// добавлении в инвойс (Продажа/Заказ) — зеркало $ioEnum['offers_type']
	// из trade (IOCore::translate('trade.goods.offers.types.opt'/'rozn')).
	// 2 (Розница) выбран по умолчанию в форме trade (invoices.html:
	// offers_types_options, "selected" при offer_type == 2).
	public const OFFER_TYPE_LABELS = [
		1 => 'Оптовое',
		2 => 'Розничное',
	];

	// ---- Общие для нескольких сущностей ----

	// Типы сущностей, к которым можно привязать задачу (Task.linkedEntityType).
	// Раньше жили как public const ENTITY_TYPE_LEAD/DEAL/ORDER/CLIENT в
	// TaskApi и дублировались вручную в TaskModal.vue, а голые числа 1/2/3
	// были зашиты прямо в Leads.vue/Deals.vue/Orders.vue при открытии задач
	// сущности (linkedEntityType: 1, // ENTITY_TYPE_LEAD и т.п.).
	public const ENTITY_TYPE_LEAD = 1;
	public const ENTITY_TYPE_DEAL = 2;
	public const ENTITY_TYPE_ORDER = 3;
	public const ENTITY_TYPE_CLIENT = 4;

	// pkid роли "Администратор" — единственной роли, которой разрешён откат
	// статуса назад.
	public const ADMIN_ROLE_PKID = 1;

	public function doLeadStatusTransitions(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusTransitions' => self::LEAD_STATUS_TRANSITIONS,
		]);
	}

	public function doLeadStatusRollback(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusRollback' => self::LEAD_STATUS_ROLLBACK,
		]);
	}

	public function doLeadRejectableStatuses(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'rejectableStatuses' => self::LEAD_REJECTABLE_STATUSES,
		]);
	}

	public function doLeadDeleteRolePkids(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'deleteRolePkids' => self::LEAD_DELETE_ROLE_PKIDS,
		]);
	}

	public function doLeadStatusLabels(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusLabels' => self::LEAD_STATUS_LABELS,
		]);
	}

	public function doLeadSourceLabels(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'sourceLabels' => self::LEAD_SOURCE_LABELS,
		]);
	}

	public function doDealStatusRollback(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusRollback' => self::DEAL_STATUS_ROLLBACK,
		]);
	}

	public function doDealDeleteRolePkids(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'deleteRolePkids' => self::DEAL_DELETE_ROLE_PKIDS,
		]);
	}

	public function doDealStatusLabels(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusLabels' => self::DEAL_STATUS_LABELS,
		]);
	}

	public function doOrderStatusTransitions(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusTransitions' => self::ORDER_STATUS_TRANSITIONS,
		]);
	}

	public function doOrderStatusRollback(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusRollback' => self::ORDER_STATUS_ROLLBACK,
		]);
	}

	public function doOrderCancellableStatuses(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'cancellableStatuses' => self::ORDER_CANCELLABLE_STATUSES,
		]);
	}

	public function doOrderDeleteRolePkids(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'deleteRolePkids' => self::ORDER_DELETE_ROLE_PKIDS,
		]);
	}

	public function doOrderLockedStatuses(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'lockedStatuses' => self::ORDER_LOCKED_STATUSES,
		]);
	}

	public function doOrderStatusLabels(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusLabels' => self::ORDER_STATUS_LABELS,
		]);
	}

	public function doInvoiceStatusLabels(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'statusLabels' => self::INVOICE_STATUS_LABELS,
		]);
	}

	public function doOfferTypeLabels(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'offerTypeLabels' => self::OFFER_TYPE_LABELS,
		]);
	}

	public function doEntityTypes(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'lead' => self::ENTITY_TYPE_LEAD,
			'deal' => self::ENTITY_TYPE_DEAL,
			'order' => self::ENTITY_TYPE_ORDER,
			'client' => self::ENTITY_TYPE_CLIENT,
		]);
	}

	public function doAdminRolePkid(ApiResult $result, array $params): ApiResult
	{
		return $result->showSuccess([
			'adminRolePkid' => self::ADMIN_ROLE_PKID,
		]);
	}
}
