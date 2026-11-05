<?php
declare(strict_types=1);

namespace App\Api;

use IO\Api\CrudApi;
use IO\Api\ApiResult;
use IO\Constants as EIO;
use App\Classes\LeadSource;

class LeadSourceApi extends CrudApi
{
    public static function struct(): array
    {
        return [
            'class' => LeadSource::class,
            'fields' => [
                'pkid' => ['type' => 'int'],
                'company_pkid' => ['type' => 'int'],
                'name' => ['type' => 'string', 'label' => 'Название'],
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
		$rules['static']['actionGetAllForLabels'] = 1;

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

    private function validateSourceName(
        ApiResult $result,
        array $params,
        int $excludePkid = 0
    ): ?ApiResult {
        $item = xarr($params, 'item', $params);

        if (!is_array($item)) {
            return $result->showResult([], 'Некорректные данные источника', EIO::VALIDATION);
        }

        $name = trim((string) xarr($item, 'name', ''));
        $companyPkid = (int) xarr($item, 'company_pkid', 0);

        if ($name === '') {
            return $result->showResult([], 'Укажите название источника', EIO::VALIDATION);
        }

        if ($companyPkid <= 0) {
            return $result->showResult([], 'Не указана компания источника', EIO::VALIDATION);
        }

		$existing = LeadSource::where('company_pkid', $companyPkid)
			->where('is_deleted', 0)
			->get();

		foreach ($existing as $source) {
			$sourcePkid = (int) $source->pkid;
			$existingName = trim((string) $source->name);

			if (
				$sourcePkid !== $excludePkid
				&& mb_strtolower($existingName) === mb_strtolower($name)
			) {
				return $result->showResult(
					[],
					'Источник с таким названием уже существует в этой компании',
					EIO::VALIDATION
				);
			}
		}

        return null;
    }

    public function doCreate(ApiResult $result, array $params): ApiResult
    {
        $validation = $this->validateSourceName($result, $params);

        if ($validation !== null) {
            return $validation;
        }

        return parent::doCreate($result, $params);
    }

    public function doSave(ApiResult $result, array $params): ApiResult
    {
        $pkid = (int) xarr($params, 'pkid', 0);
        $validation = $this->validateSourceName($result, $params, $pkid);

        if ($validation !== null) {
            return $validation;
        }

        return parent::doSave($result, $params);
    }

	public function doGetAllForLabels(ApiResult $result, array $params): ApiResult
	{
		$companyPkid = (int) xarr($params, 'company_pkid', 0);

		if ($companyPkid <= 0) {
			return $result->showResult([], 'Не указана компания', EIO::VALIDATION);
		}

		$items = LeadSource::where('company_pkid', $companyPkid)
			->orderBy('pkid')
			->get(['pkid', 'name', 'is_deleted']);

		return $result->showResult(
			['items' => $items],
			'',
			EIO::OK
		);
	}
}
