<?php
declare(strict_types=1);
namespace App\Api;

use IO\Constants as EIO;
use IO\Api\CrudApi;
use IO\Api\ApiResult;
use App\Classes\LeadStage;
use App\Classes\AuditLog;

class LeadStageApi extends CrudApi
{
	public static function struct(): array
	{
		return [
			'class' => LeadStage::class,
			'fields' => [
				'pkid' => ['type' => 'int'],
				'company_pkid' => ['type' => 'int'],
				'funnel_pkid' => ['type' => 'int', 'label' => 'Воронка'],
				'name' => ['type' => 'string', 'label' => 'Название этапа'],
				'sortOrder' => ['type' => 'int', 'label' => 'Порядок'],
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
		$rules['static']['actionReorder'] = 1;

		return $rules;
	}

	public function onSearchFilter(array $filter): array
	{
		$filter['is_deleted'] = 0;
		return $filter;
	}

	public function onUpdateData(array $save_data, array $update_data): array
	{
		if (empty($this->item_pkid)) {
			$update_data['gmtimeAdd'] = gmdate('Y-m-d H:i:s');
			$update_data['ugmtimeAdd'] = time();
			$update_data['is_deleted'] = 0;
		}

		$update_data['gmtimeChange'] = gmdate('Y-m-d H:i:s');
		$update_data['ugmtimeChange'] = time();

		return $update_data;
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

				AuditLog::record($companyPkid, $userPkid, AuditLog::ENTITY_LEAD_STAGE, $pkid, AuditLog::ACTION_CREATE, null, $item);
			} catch (\Throwable $e) {
				// ignore error
			}
		}
		return $res;
	}

	public function doReorder(ApiResult $result, array $params): ApiResult
	{
		$pkids = xarr($params, 'pkids', []);

		if (!is_array($pkids) || empty($pkids)) {
			return $result->showResult([], 'pkids не переданы', EIO::VALIDATION);
		}

		foreach ($pkids as $index => $pkid) {
			$pkid = (int) $pkid;
			if ($pkid <= 0) continue;

			LeadStage::updateByFilter(
				['pkid' => $pkid, 'is_deleted' => 0],
				[
					'sortOrder' => $index,
					'gmtimeChange' => gmdate('Y-m-d H:i:s'),
					'ugmtimeChange' => time(),
				]
			);
		}

		return $result->showSuccess([
			'pkids' => array_values(array_map('intval', $pkids)),
		]);
	}
}
