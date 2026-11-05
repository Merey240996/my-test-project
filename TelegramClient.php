<?php
declare(strict_types=1);
namespace App\Classes;

class TelegramClient
{
	public static function call(string $token, string $method, array $params = []): array
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
}
