<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SSEController extends Controller
{
	/**
	 * Stream Server-Sent Events for real-time message updates.
	 */
	public function stream(Request $request)
	{
		if (!Auth::check()) {
			abort(401, 'Unauthorized');
		}

		$config = config('database.connections.pgsql');

		if (!$config || DB::connection()->getDriverName() !== 'pgsql') {
			return response()->json(['error' => 'PostgreSQL connection not available'], 500);
		}

		return response()->stream(function () use ($config) {
			// confirm that the connection was established
			$this->sendSSE('connected', ['status' => 'connected']);

			// connect to PostgreSQL
			$connectionString = sprintf(
				"host=%s port=%s dbname=%s user=%s password=%s",
				$config['host'],
				$config['port'],
				$config['database'],
				$config['username'],
				$config['password']
			);

			// try to connect to PostgreSQL and suppress errors, so they don't break SSE
			$pgConnection = @pg_connect($connectionString);

			if (!$pgConnection) {
				$this->sendSSE('error', ['message' => 'Failed to connect to database']);
				return;
			}

			// LISTEN for notifications (NOTIFY implemented via a trigger in migrations)
			pg_query($pgConnection, "LISTEN new_message");

			$lastKeepAlive = time();

			while (true) {
				// check if we should stop
				if (connection_aborted()) {
					break;
				}

				// check if there are new messages
				$notify = pg_get_notify($pgConnection);

				if ($notify) {
					Log::info('SSE received notification', ['notify' => $notify]);
					if (!is_array($notify)) {
						$notify = json_decode($notify, true);
					}

					// extract the message payload
					$payload = json_decode($notify['payload'], true);

					Log::info('SSE parsed payload', ['payload' => $payload]);

					if ($payload && isset($payload['id'])) {
						try {
							$message = Message::with('user:id,username,github_avatar_url')
								->find($payload['id']);

							if ($message) {
								$this->sendSSE('message', [
									'id' => $message->id,
									'user_id' => $message->user_id,
									'username' => $message->user->username,
									'avatar_url' => $message->user->github_avatar_url,
									'content' => e($message->content),
									'created_at' => $message->created_at->toIso8601String(),
								]);

								Log::info('SSE sent message', ['message_id' => $message->id]);
							} else {
								Log::warning('SSE message not found', ['id' => $payload['id']]);
							}
						} catch (\Exception $e) {
							Log::error('SSE message fetch error: ' . $e->getMessage());
							$this->sendSSE('error', ['message' => 'Error fetching message']);
						}
					} else {
						Log::warning('SSE invalid payload', ['payload' => $payload]);
					}
				}

				// send keep-alive every 30 seconds
				if ((time() - $lastKeepAlive) >= 30) {
					$this->sendSSE('keepalive', ['timestamp' => time()]);
					$lastKeepAlive = time();
				}

				// prevent CPU spinning (checking for updates too quickly)
				usleep(250000); // 250ms
			}

			// clean up
			pg_query($pgConnection, "UNLISTEN new_message");
			pg_close($pgConnection);
		}, 200, [
			'Content-Type' => 'text/event-stream',
			'Cache-Control' => 'no-cache',
			'Connection' => 'keep-alive',
			'X-Accel-Buffering' => 'no',
		]);
	}

	/**
	 * Send a Server-Sent Event message.
	 */
	private function sendSSE(string $event, array $data): void
	{
		echo "event: {$event}\n";
		echo "data: " . json_encode($data) . "\n\n";

		if (ob_get_level() > 0) {
			ob_flush();
		}
		flush();
	}
}
