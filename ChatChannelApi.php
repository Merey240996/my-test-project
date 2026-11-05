<?php
declare(strict_types=1);
namespace App\Api;

use IO\Api\CrudApi;
use IO\Api\ApiResult;
use IO\Constants as EIO;
use App\Classes\ChatChannel;

class ChatChannelApi extends CrudApi
{
	// Публичный адрес бэкенда для вебхуков (для боевого запуска вынести в настройки)
	private const PUBLIC_BASE_URL = 'https://crm2.devkdt.info';

	public static function struct(): array
	{
		// token и secret намеренно не описаны: через общий API их не получить
		return [
			'class' => ChatChannel::class,
			'fields' => [
				'pkid' => ['type' => 'int'],
				'company_pkid' => ['type' => 'int'],
				'type' => ['type' => 'int'],
				'name' => ['type' => 'string'],
				'status' => ['type' => 'int'],
				'last_error' => ['type' => 'string'],
				'is_deleted' => ['type' => 'int', 'virtual' => true],
			],
			'primary' => ['pkid'],
			'soft_delete' => true,
		];
	}

	public static function rules(): array
	{
		$rules = parent::rules();
		$rules['static']['actionList'] = 1;
		$rules['static']['actionConnect'] = 1;
		$rules['static']['actionDisconnect'] = 1;
		return $rules;
	}

	/** Запрос к Telegram Bot API */
	private function tg(string $token, string $method, array $params = []): array
	{
		$ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($params),
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 10,
		]);
		$res = curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);
		if ($res === false) {
			return ['ok' => false, 'description' => $err ?: 'нет связи с Telegram'];
		}
		$json = json_decode((string) $res, true);
		return is_array($json) ? $json : ['ok' => false, 'description' => 'некорректный ответ Telegram'];
	}

	/** Список каналов компании (без токена и секрета) */
	public function doList(ApiResult $result, array $params): ApiResult
	{
		$companyPkid = (int) xarr($params, 'company_pkid', 0);
		if ($companyPkid <= 0) {
			return $result->showResult([], 'Не указана компания', EIO::VALIDATION);
		}
		$items = ChatChannel::where('company_pkid', $companyPkid)
			->where('is_deleted', 0)
			->orderBy('pkid')
			->get(['pkid', 'type', 'name', 'status', 'last_error']);
		return $result->showSuccess(['items' => $items]);
	}

	/** Подключить Telegram-бота: проверить токен и зарегистрировать вебхук */
	public function doConnect(ApiResult $result, array $params): ApiResult
	{
		try {
			$companyPkid = (int) xarr($params, 'company_pkid', 0);
			$token = trim((string) xarr($params, 'token', ''));

			if ($companyPkid <= 0) {
				return $result->showResult([], 'Не указана компания', EIO::VALIDATION);
			}
			if (!preg_match('/^\d{6,12}:[A-Za-z0-9_-]{30,50}$/', $token)) {
				return $result->showResult([], 'Токен бота выглядит неверно', EIO::VALIDATION);
			}
			if (ChatChannel::where('token', $token)->where('is_deleted', 0)->exists()) {
				return $result->showResult([], 'Этот бот уже подключён', EIO::VALIDATION);
			}

			$me = $this->tg($token, 'getMe');
			if (empty($me['ok'])) {
				return $result->showResult([], 'Telegram не принял токен: ' . ($me['description'] ?? '?'), EIO::VALIDATION);
			}
			$username = (string) ($me['result']['username'] ?? '');

			$now = gmdate('Y-m-d H:i:s');
			$ts = time();
			$channel = new ChatChannel();
			$channel->company_pkid = $companyPkid;
			$channel->type = 1;
			$channel->name = $username !== '' ? '@' . $username : 'Telegram';
			$channel->token = $token;
			$channel->secret = bin2hex(random_bytes(16));
			$channel->status = 0;
			$channel->is_deleted = 0;
			$channel->gmtimeAdd = $now;
			$channel->gmtimeChange = $now;
			$channel->ugmtimeAdd = $ts;
			$channel->ugmtimeChange = $ts;
			$channel->save();

			$url = self::PUBLIC_BASE_URL . '/hooks/chat/telegram/' . (int) $channel->pkid . '/' . $channel->secret;
			$set = $this->tg($token, 'setWebhook', [
				'url' => $url,
				'allowed_updates' => ['message'],
				'drop_pending_updates' => true,
			]);

			if (empty($set['ok'])) {
				$channel->status = 2;
				$channel->last_error = mb_substr((string) ($set['description'] ?? 'ошибка вебхука'), 0, 500);
				$channel->is_deleted = 1;
				$channel->save();
				return $result->showResult([], 'Не удалось настроить вебхук: ' . ($set['description'] ?? '?'), EIO::VALIDATION);
			}

			$channel->status = 1;
			$channel->save();

			return $result->showSuccess([
				'pkid' => (int) $channel->pkid,
				'name' => $channel->name,
			]);
		} catch (\Throwable $e) {
			return $result->showResult([], $e->getMessage(), EIO::VALIDATION);
		}
	}

	/** Отключить канал: снять вебхук и скрыть запись */
	public function doDisconnect(ApiResult $result, array $params): ApiResult
	{
		try {
			$companyPkid = (int) xarr($params, 'company_pkid', 0);
			$pkid = (int) xarr($params, 'pkid', 0);
			$channel = ChatChannel::where('pkid', $pkid)
				->where('company_pkid', $companyPkid)
				->where('is_deleted', 0)
				->first();
			if (!$channel) {
				return $result->showResult([], 'Канал не найден', EIO::VALIDATION);
			}
			$this->tg((string) $channel->token, 'deleteWebhook');
			$channel->status = 0;
			$channel->is_deleted = 1;
			$channel->gmtimeChange = gmdate('Y-m-d H:i:s');
			$channel->ugmtimeChange = time();
			$channel->save();
			return $result->showSuccess(['pkid' => $pkid]);
		} catch (\Throwable $e) {
			return $result->showResult([], $e->getMessage(), EIO::VALIDATION);
		}
	}
}
