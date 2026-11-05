<?php
declare(strict_types=1);
namespace App\Routes;

use IO\Route;
use App\Classes\ChatChannel;
use App\Classes\ChatConversation;
use App\Classes\ChatMessage;

class ChatWebhookRoute extends Route
{
	public static function routes(\IO\App $app): void
	{
		$app->add_route([
			'url' => '/hooks/chat/ping',
			'type' => ['get'],
			'class_name' => static::class,
			'method' => 'actionPing',
			'nosession' => true,
		]);
		// Telegram: /hooks/chat/telegram/<номер канала>/<секрет>
		$app->add_route([
			'url' => '/hooks/chat/telegram/:channel/:secret',
			'type' => ['post'],
			'class_name' => static::class,
			'method' => 'actionTelegram',
			'nosession' => true,
		]);
	}

	public function actionPing(): mixed
	{
		return $this->json(['ok' => true, 'time' => gmdate('c')]);
	}

	/**
	 * Приём входящих сообщений Telegram. Всегда отвечаем 200, если запрос
	 * подлинный: иначе Telegram будет повторять отправку.
	 */
	public function actionTelegram(): mixed
	{
		$channelPkid = (int) ($this->args['channel'] ?? 0);
		$secret = (string) ($this->args['secret'] ?? '');

		$channel = ChatChannel::where('pkid', $channelPkid)
			->where('type', 1)
			->where('is_deleted', 0)
			->first();
		if (!$channel || $secret === '' || !hash_equals((string) $channel->secret, $secret)) {
			return $this->json(['ok' => false], 403);
		}

		try {
			$update = json_decode((string) file_get_contents('php://input'), true);
			$msg = is_array($update) ? ($update['message'] ?? null) : null;
			$chat = is_array($msg) ? ($msg['chat'] ?? []) : [];
			$text = is_array($msg) ? trim((string) ($msg['text'] ?? '')) : '';

			// Пока обрабатываем только личные текстовые сообщения
			if ($text === '' || ($chat['type'] ?? '') !== 'private' || empty($chat['id'])) {
				return $this->json(['ok' => true, 'ignored' => true]);
			}

			$now = gmdate('Y-m-d H:i:s');
			$ts = time();
			$externalChatId = (string) $chat['id'];
			$externalMsgId = (string) ($msg['message_id'] ?? '');

			$title = trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''));
			if ($title === '') {
				$title = !empty($chat['username']) ? '@' . $chat['username'] : 'Telegram ' . $externalChatId;
			}

			$conv = ChatConversation::where('channel_pkid', (int) $channel->pkid)
				->where('external_chat_id', $externalChatId)
				->first();

			if (!$conv) {
				$conv = new ChatConversation();
				$conv->company_pkid = (int) $channel->company_pkid;
				$conv->channel_pkid = (int) $channel->pkid;
				$conv->external_chat_id = $externalChatId;
				$conv->title = mb_substr($title, 0, 255);
				$conv->unread_count = 0;
				$conv->is_deleted = 0;
				$conv->gmtimeAdd = $now;
				$conv->ugmtimeAdd = $ts;
			} elseif ($externalMsgId !== '' && ChatMessage::where('conversation_pkid', (int) $conv->pkid)
				->where('external_message_id', $externalMsgId)->exists()) {
				// Telegram повторил то же сообщение: не дублируем
				return $this->json(['ok' => true, 'duplicate' => true]);
			}

			$conv->last_message_text = mb_substr($text, 0, 500);
			$conv->last_message_at = $now;
			$conv->unread_count = (int) $conv->unread_count + 1;
			$conv->gmtimeChange = $now;
			$conv->ugmtimeChange = $ts;
			$conv->save();

			$m = new ChatMessage();
			$m->company_pkid = (int) $channel->company_pkid;
			$m->conversation_pkid = (int) $conv->pkid;
			$m->direction = 1; // входящее
			$m->text = mb_substr($text, 0, 4000);
			$m->external_message_id = $externalMsgId !== '' ? $externalMsgId : null;
			$m->is_deleted = 0;
			$m->gmtimeAdd = $now;
			$m->gmtimeChange = $now;
			$m->ugmtimeAdd = $ts;
			$m->ugmtimeChange = $ts;
			$m->save();

			return $this->json(['ok' => true]);
		} catch (\Throwable $e) {
			// Для разработки показываем причину; секрет уже проверен
			return $this->json(['ok' => false, 'error' => $e->getMessage()]);
		}
	}
}
