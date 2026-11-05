<?php
declare(strict_types=1);
namespace App\Api;

use IO\Api\CrudApi;
use IO\Api\ApiResult;
use IO\Constants as EIO;
use App\Classes\ChatChannel;
use App\Classes\ChatConversation;
use App\Classes\ChatMessage;
use App\Classes\TelegramClient;

class ChatApi extends CrudApi
{
	public static function struct(): array
	{
		return [
			'class' => ChatConversation::class,
			'fields' => [
				'pkid' => ['type' => 'int'],
				'company_pkid' => ['type' => 'int'],
				'channel_pkid' => ['type' => 'int'],
				'title' => ['type' => 'string'],
				'is_deleted' => ['type' => 'int', 'virtual' => true],
			],
			'primary' => ['pkid'],
			'soft_delete' => true,
		];
	}

	public static function rules(): array
	{
		$rules = parent::rules();
		$rules['static']['actionConversations'] = 1;
		$rules['static']['actionMessages'] = 1;
		$rules['static']['actionSend'] = 1;
		return $rules;
	}

	/** Список диалогов компании (свежие сверху) */
	public function doConversations(ApiResult $result, array $params): ApiResult
	{
		$companyPkid = (int) xarr($params, 'company_pkid', 0);
		if ($companyPkid <= 0) {
			return $result->showResult([], 'Не указана компания', EIO::VALIDATION);
		}
		$limit = min(max((int) xarr($params, 'limit', 50), 1), 100);

		$items = ChatConversation::where('company_pkid', $companyPkid)
			->where('is_deleted', 0)
			->orderByDesc('last_message_at')
			->orderByDesc('pkid')
			->limit($limit)
			->get(['pkid', 'channel_pkid', 'title', 'last_message_text', 'last_message_at', 'unread_count']);

		$types = ChatChannel::whereIn('pkid', $items->pluck('channel_pkid')->unique()->all())
			->pluck('type', 'pkid');

		$out = [];
		foreach ($items as $c) {
			$out[] = [
				'pkid' => (int) $c->pkid,
				'type' => (int) ($types[$c->channel_pkid] ?? 1),
				'title' => $c->title,
				'last_message_text' => $c->last_message_text,
				'last_message_at' => $c->last_message_at,
				'unread_count' => (int) $c->unread_count,
			];
		}
		return $result->showSuccess(['items' => $out]);
	}

	/** Последние сообщения диалога; по умолчанию помечает диалог прочитанным */
	public function doMessages(ApiResult $result, array $params): ApiResult
	{
		$companyPkid = (int) xarr($params, 'company_pkid', 0);
		$convPkid = (int) xarr($params, 'conversation_pkid', 0);

		$conv = ChatConversation::where('pkid', $convPkid)
			->where('company_pkid', $companyPkid)
			->where('is_deleted', 0)
			->first();
		if (!$conv) {
			return $result->showResult([], 'Диалог не найден', EIO::VALIDATION);
		}

		$rows = ChatMessage::where('conversation_pkid', $convPkid)
			->where('is_deleted', 0)
			->orderByDesc('pkid')
			->limit(100)
			->get(['pkid', 'direction', 'text', 'gmtimeAdd'])
			->reverse()
			->values();

		if ((int) xarr($params, 'mark_read', 1) === 1 && (int) $conv->unread_count > 0) {
			$conv->unread_count = 0;
			$conv->save();
		}
		return $result->showSuccess(['items' => $rows]);
	}

	/** Ответ менеджера: отправить клиенту в Telegram и сохранить в историю */
	public function doSend(ApiResult $result, array $params): ApiResult
	{
		try {
			$companyPkid = (int) xarr($params, 'company_pkid', 0);
			$convPkid = (int) xarr($params, 'conversation_pkid', 0);
			$text = trim((string) xarr($params, 'text', ''));
			if ($text === '' || mb_strlen($text) > 4000) {
				return $result->showResult([], 'Сообщение пустое или слишком длинное', EIO::VALIDATION);
			}

			$conv = ChatConversation::where('pkid', $convPkid)
				->where('company_pkid', $companyPkid)
				->where('is_deleted', 0)
				->first();
			if (!$conv) {
				return $result->showResult([], 'Диалог не найден', EIO::VALIDATION);
			}

			$channel = ChatChannel::where('pkid', (int) $conv->channel_pkid)
				->where('type', 1)
				->where('is_deleted', 0)
				->first();
			if (!$channel || (int) $channel->status !== 1) {
				return $result->showResult([], 'Канал Telegram не подключён', EIO::VALIDATION);
			}

			$res = TelegramClient::call((string) $channel->token, 'sendMessage', [
				'chat_id' => (string) $conv->external_chat_id,
				'text' => $text,
			]);
			if (empty($res['ok'])) {
				return $result->showResult([], 'Telegram не принял сообщение: ' . ($res['description'] ?? '?'), EIO::VALIDATION);
			}

			$now = gmdate('Y-m-d H:i:s');
			$ts = time();
			$msgId = (string) ($res['result']['message_id'] ?? '');

			$m = new ChatMessage();
			$m->company_pkid = $companyPkid;
			$m->conversation_pkid = $convPkid;
			$m->direction = 2; // исходящее
			$m->text = $text;
			$m->external_message_id = $msgId !== '' ? $msgId : null;
			$m->user_pkid = (int) $this->app()->session->getUserID();
			$m->is_deleted = 0;
			$m->gmtimeAdd = $now;
			$m->gmtimeChange = $now;
			$m->ugmtimeAdd = $ts;
			$m->ugmtimeChange = $ts;
			$m->save();

			$conv->last_message_text = mb_substr($text, 0, 500);
			$conv->last_message_at = $now;
			$conv->gmtimeChange = $now;
			$conv->ugmtimeChange = $ts;
			$conv->save();

			return $result->showSuccess(['pkid' => (int) $m->pkid]);
		} catch (\Throwable $e) {
			return $result->showResult([], $e->getMessage(), EIO::VALIDATION);
		}
	}
}
