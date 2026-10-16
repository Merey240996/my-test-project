<?php
declare(strict_types=1);
namespace App\Api;

use IO\Constants as EIO;
use IO\Api\CrudApi;
use IO\Api\ApiResult;
use App\Classes\ClientBase;
use App\Classes\UserRole;
use App\Classes\ClientBasePermissions;
use App\Classes\RolePermission;

class ClientBaseApi extends CrudApi
{
	private const RESOURCE_TYPE = 'core_crm_funnels';

	public static function struct(): array
	{
		return [
			'class' => ClientBase::class,
			'fields' => [
				'pkid' => ['type' => 'int'],
				'company_pkid' => ['type' => 'int'],
				'name' => ['type' => 'string', 'label' => 'Название базы'],
				'is_company' => ['type' => 'int', 'label' => 'База является компанией'],
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
		$rules['static']['actionGetAccessibleBaseIds'] = 1;
		$rules['static']['actionGetReport'] = 1;
		$rules['static']['actionImport'] = 1;
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

	public function onUpdateData(array $save_data, array $update_data): array
	{
		$update_data['gmtimeAdd'] = gmdate('Y-m-d H:i:s');
		$update_data['ugmtimeAdd'] = time();
		$update_data['is_deleted'] = 0;
		$update_data['gmtimeChange'] = gmdate('Y-m-d H:i:s');
		$update_data['ugmtimeChange'] = time();

		return $update_data;
	}

	/**
	 * Открытие карточки конкретной базы — тут уже проверяем реальный доступ + право чтения.
	 */
	public function doGetByID(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);

		if ($pkid <= 0) {
			return $result->showResult([], 'pkid не передан', EIO::VALIDATION);
		}

		$model = ClientBase::where('pkid', $pkid)
			->where('is_deleted', 0)
			->first();

		if (!$model) {
			return $result->showResult(['item' => null], 'Item not found', EIO::NOTFOUND_OBJECT);
		}

		$company_pkid = (int) $model->company_pkid;

		if (!$this->hasAccessToBase($pkid, $company_pkid)) {
			return $result->showResult([], 'Нет доступа к этой базе', EIO::DENY);
		}

		if (!$this->can('canRead', $company_pkid)) {
			return $result->showResult([], 'Нет прав на просмотр баз', EIO::DENY);
		}

		return $result->showSuccess(['item' => $model]);
	}

	/**
	 * Редактирование — доступ к базе + право canEdit.
	 */
	public function doSave(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		try {
			$oldItem = $pkid > 0 ? (ClientBase::where('pkid', $pkid)->first()?->toArray() ?? null) : null;
		} catch (\Throwable $e) {
			$oldItem = null;
		}

		if ($pkid > 0) {
			$model = ClientBase::where('pkid', $pkid)
				->where('is_deleted', 0)
				->first();

			if ($model) {
				$company_pkid = (int) $model->company_pkid;

				if (!$this->hasAccessToBase($pkid, $company_pkid)) {
					return $result->showResult([], 'Нет доступа к этой базе', EIO::DENY);
				}

				if (!$this->can('canEdit', $company_pkid)) {
					return $result->showResult([], 'Нет прав на редактирование баз', EIO::DENY);
				}
			}
		}

		$res = parent::doSave($result, $params);
		if ($res->error_code === EIO::OK) {
			try {
				$item = $res->toArray();
				$savedPkid = (int) xarr($item, 'pkid', $pkid);
				$session = $this->app()->session;
				$userPkid = (int) ($session ? ($session->getUserID() ?? 0) : 0);
				$companyPkid = (int) xarr($item, 'company_pkid', 1);

				$action = $pkid > 0 ? \App\Classes\AuditLog::ACTION_UPDATE : \App\Classes\AuditLog::ACTION_CREATE;
				\App\Classes\AuditLog::record($companyPkid, $userPkid, \App\Classes\AuditLog::ENTITY_CLIENT_BASE, $savedPkid, $action, $oldItem, $item);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	/**
	 * Удаление — доступ к базе + право canDelete.
	 */
	public function doDelete(ApiResult $result, array $params): ApiResult
	{
		$pkid = (int) xarr($params, 'pkid', 0);
		try {
			$oldItem = $pkid > 0 ? (ClientBase::where('pkid', $pkid)->first()?->toArray() ?? null) : null;
		} catch (\Throwable $e) {
			$oldItem = null;
		}

		if ($pkid > 0) {
			$model = ClientBase::where('pkid', $pkid)
				->where('is_deleted', 0)
				->first();

			if ($model) {
				$company_pkid = (int) $model->company_pkid;

				if (!$this->hasAccessToBase($pkid, $company_pkid)) {
					return $result->showResult([], 'Нет доступа к этой базе', EIO::DENY);
				}

				if (!$this->can('canDelete', $company_pkid)) {
					return $result->showResult([], 'Нет прав на удаление баз', EIO::DENY);
				}
			}
		}

		$res = parent::doDelete($result, $params);
		if ($res->error_code === EIO::OK && $oldItem) {
			try {
				$session = $this->app()->session;
				$userPkid = (int) ($session ? ($session->getUserID() ?? 0) : 0);
				$companyPkid = (int) xarr($oldItem, 'company_pkid', 1);

				\App\Classes\AuditLog::record($companyPkid, $userPkid, \App\Classes\AuditLog::ENTITY_CLIENT_BASE, $pkid, \App\Classes\AuditLog::ACTION_DELETE, $oldItem, null);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	/**
	 * Экспорт — требует canExport.
	 */
	public function doGetReport(ApiResult $result, array $params): ApiResult
	{
		$company_pkid = (int) xarr($params, 'company_pkid', 0);

		if (!$this->can('canExport', $company_pkid)) {
			return $result->showResult([], 'Нет прав на экспорт баз', EIO::DENY);
		}

		$bases = ClientBase::where('company_pkid', $company_pkid)
			->where('is_deleted', 0)
			->get();

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Базы клиентов');

		// Заголовки
		$sheet->setCellValue('A1', 'Название базы');
		$sheet->setCellValue('B1', 'Тип базы');

		// Данные
		$row = 2;

		foreach ($bases as $base) {
			$sheet->setCellValue('A' . $row, (string) $base->name);
			$sheet->setCellValue(
				'B' . $row,
				$base->is_company ? 'Компания' : 'Физлицо'
			);

			$row++;
		}

		// Автоширина колонок
		$sheet->getColumnDimension('A')->setAutoSize(true);
		$sheet->getColumnDimension('B')->setAutoSize(true);

		// Жирные заголовки
		$sheet->getStyle('A1:B1')->getFont()->setBold(true);

		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter(
			$spreadsheet,
			'Xlsx'
		);

		ob_start();
		$writer->save('php://output');
		$fileContent = ob_get_clean();

		return $result->showResult([
			'file_content' => base64_encode($fileContent),
			'filename'     => 'client_bases_' . date('Y-m-d_H-i') . '.xlsx',
			'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		]);
	}

	/**
	 * Импорт баз из XLS.
	 */
	public function doImport(ApiResult $result, array $params): ApiResult
	{
		try {
			$company_pkid = (int)($params['company_pkid'] ?? 0);

			if ($company_pkid <= 0) {
				return $result->showResult(
					['error' => 'Не указан company_pkid'],
					'Ошибка',
					EIO::ERROR
				);
			}

			$tmpFile = $_FILES['file']['tmp_name'] ?? '';

			if (!$tmpFile || !file_exists($tmpFile)) {
				return $result->showResult(
					['error' => 'Файл не найден'],
					'Ошибка',
					EIO::ERROR
				);
			}

			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpFile);
			$sheet = $spreadsheet->getActiveSheet();

			$highestRow = $sheet->getHighestRow();

			$created = 0;
			$skipped = 0;

			// Начинаем со второй строки, первая — заголовки
			for ($row = 2; $row <= $highestRow; $row++) {

				$name = trim((string)$sheet->getCell('A' . $row)->getValue());
				$type = trim((string)$sheet->getCell('B' . $row)->getValue());

				// Пропускаем пустые строки
				if ($name === '') {
					$skipped++;
					continue;
				}

				$is_company = ($type === 'Компания') ? 1 : 0;

				$exists = ClientBase::where('company_pkid', $company_pkid)
					->where('name', $name)
					->where('is_deleted', 0)
					->first();

				if ($exists) {
					$skipped++;
					continue;
				}

				$model = new ClientBase();

				$model->company_pkid = $company_pkid;
				$model->name = $name;
				$model->is_company = $is_company;
				$model->is_deleted = 0;

				$model->save();

				$created++;
			}

			return $result->showSuccess([
				'created' => $created,
				'skipped' => $skipped,
			]);

		} catch (\Throwable $e) {
			return $result->showResult([
				'exception' => get_class($e),
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
			], 'Ошибка импорта', EIO::ERROR);
		}
	}

	/**
	 * Есть ли у текущего пользователя доступ к конкретной базе
	 * (объединение по всем его ролям в этой компании).
	 */
	private function hasAccessToBase(int $base_pkid, int $company_pkid): bool
	{
		$allowed = $this->getAllowedBasePkids($company_pkid);

		// null = ограничений нет (полный доступ)
		if ($allowed === null) {
			return true;
		}

		return in_array($base_pkid, $allowed, true);
	}

	/**
	 * @return array<int>|null null — ограничений нет (полный доступ),
	 *                         []  — доступа нет ни к одной базе
	 */
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

		$base_pkids = ClientBasePermissions::where('company_pkid', $company_pkid)
			->whereIn('role_pkid', $role_pkids)
			->where('is_deleted', 0)
			->pluck('base_pkid')
			->map(fn ($v) => (int) $v)
			->unique()
			->values()
			->all();

		return $base_pkids;
	}

	/**
	 * Агрегированные (OR по всем ролям пользователя) флаги действий
	 * для resource_type = self::RESOURCE_TYPE.
	 *
	 * @return array<string,bool>
	 */
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

	public function doGetAccessibleBaseIds(ApiResult $result, array $params): ApiResult
	{
		$company_pkid = (int) xarr($params, 'company_pkid', 0);
		$allowed = $this->getAllowedBasePkids($company_pkid);
		$permissions = $this->getPermissionFlags($company_pkid);

		return $result->showSuccess([
			'full_access' => $allowed === null,
			'base_pkids' => $allowed ?? [],
			'permissions' => $permissions,
		]);
	}

	public function doCreate(ApiResult $result, array $params): ApiResult
	{
		$res = parent::doCreate($result, $params);

		if ($res->error_code === EIO::OK) {
			try {
				$item = $res->toArray();
				$pkid = (int) xarr($item, 'pkid', 0);
				$session = $this->app()->session;
				$userPkid = (int) ($session ? ($session->getUserID() ?? 0) : 0);
				$companyPkid = (int) xarr($item, 'company_pkid', 1);

				\App\Classes\AuditLog::record($companyPkid, $userPkid, \App\Classes\AuditLog::ENTITY_CLIENT_BASE, $pkid, \App\Classes\AuditLog::ACTION_CREATE, null, $item);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}
}
