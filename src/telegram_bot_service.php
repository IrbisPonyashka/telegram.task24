<?php

	require_once __DIR__ . '/settings.php';
	require __DIR__ . '/vendor/autoload.php';

	use Telegram\Bot\Api;

	// use Jenssegers\Blade\Blade;

	class TelegramBotService
	{
		private static $dbInstance = null;

		private static Api $api;

		public function __construct()
		{
			self::$api = new Api(TG_BOT_API_KEY);
		}
	
		public static function getDBInstance(): ?mysqli
		{
			if (self::$dbInstance == null) {
				self::$dbInstance = new mysqli(DB_HOST, DB_LOGIN, DB_PASSWORD, DB_NAME);
			}

			return self::$dbInstance;
		}

        public function processMessage($message)
        {
            // Проверяем, есть ли пересланное сообщение
            $isForwarded = $message->getForwardFrom() || $message->getForwardFromChat();

            // Получаем текст сообщения
            $text = $message->getText() ?? $message->getCaption() ?? '';

            $findDateInTextResult = $this->findDateInText($text);
            if ($findDateInTextResult["status"] != "success") {
                return $findDateInTextResult;
            }

            $dateString = $findDateInTextResult["date"] ?? null;

            if ($dateString) {
//                $text = preg_replace('/#(\d{2}-\d{2}-\d{4}(?: \d{2}:\d{2})?|\d{2}:\d{2})/', '', $text);
                $text = preg_replace('/#(\d{2}-\d{2}(?:-\d{2,4})?(?: \d{2}:\d{2})?)/', '', $text);

                $text = trim(preg_replace('/\n{2,}/', "\n", $text));
            }

            // Проверяем, есть ли в сообщении медиафайлы (фото, документы)
            $photos = $message->getPhoto();
            $document = $message->getDocument();

            $photoUrl = null;
            $documentUrl = null;
            $maxSize = 5 * 1024 * 1024; // 5MB в байтах

            // Получаем лучшее фото
            if (!empty($photos)) {
                $bestPhoto = $photos[3]; // Берем фото с наибольшим качеством

                if ($bestPhoto['file_size'] > $maxSize) {
                    return [
                        'status' => 'error',
                        'message' => "❌ Файл превышает лимит 5MB."
                    ];
                }

                $photoUrl = $this->getFileUrl($bestPhoto['file_id']);
            }

            // Получаем документ
            if (!empty($document)) {
                if ($document['file_size'] > $maxSize) {
                    return [
                        'status' => 'error',
                        'message' => "❌ Файл превышает лимит 5MB."
                    ];
                }

                $documentUrl = $this->getFileUrl($document['file_id']);
                $fileName = $document['file_name'];
            }

            // Если есть фото/документ, но нет текста — отправляем ошибку
            if (($photoUrl || $documentUrl) && empty($text)) {
                return [
                    'status' => 'error',
                    'message' => "Я пока не умею работать с картинками и файлами без текста. 😔"
                ];
            }

            // Извлекаем ссылки
            preg_match_all('/https?:\/\/\S+/', $text, $matches);
            $links = !empty($matches[0]) ? implode("\n", $matches[0]) : '';

            // Если текст содержит переносы строк, делаем заголовок и описание
            $lines = explode("\n", $text);
            $title = trim($lines[0]); // Первое предложение — заголовок
            $description = trim(implode("\n", array_slice($lines, 1))); // Остальное — описание

            // Формируем описание задачи
            if (!empty($links)) {
                $description .= "\n\n🔗 Полезные ссылки:\n" . $links;
            }

            // Убираем HTML-теги, если вдруг в тексте что-то прилетело
            $title = strip_tags($title);
            $description = strip_tags($description);

            // Возвращаем готовую задачу
            return [
                'status' => 'success',
                'is_forwarded' => $isForwarded,
                'title' => $title ?: 'Новая задача из Telegram',
                'description' => $description ?: 'Без описания',
                'deadline' => $dateString,
                'photoUrl' => $photoUrl,
                'documentUrl' => $documentUrl
            ];
        }
        public static function findDateInText($text): array
        {
            // Регулярка для поиска всех форматов
            preg_match('/#(\d{2}-\d{2}(?:-\d{2,4})?(?: \d{2}:\d{2})?)/', $text, $dateMatch);

//            $dateString = $dateMatch[1] ?? null;
//            if (!$dateString) {
//                return [
//                    'status' => 'error',
//                    'message' => "❌ *Неверный формат даты:* `{$text}`\n\nИспользуйте:\n🔹 `#дд-мм`\n🔹 `#дд-мм-ГГ`\n🔹 `#дд-мм-ГГГГ`\n🔹 `#дд-мм чч:мм`\n🔹 `#дд-мм-ГГ чч:мм`\n🔹 `#дд-мм-ГГГГ чч:мм`"
//                ];
//            }

            $dateString = $dateMatch[1] ?? null;
            if (!$dateString) {
                // Если даты нет, просто возвращаем текст без изменений
                return [
                    'status' => 'success',
                    'date' => null
                ];
            }

            $now = new DateTime();
            $timezone = $now->getTimezone();
            $convertedDate = null;

            if (preg_match('/^(\d{2})-(\d{2})$/', $dateString, $parts)) {
                // Формат: dd-mm (подставляем текущий год)
                $day = $parts[1];
                $month = $parts[2];
                $year = $now->format('Y');
                $dateObject = DateTime::createFromFormat('d-m-Y', "$day-$month-$year", $timezone);
            } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $dateString, $parts)) {
                // Формат: dd-mm-YY (подставляем правильный век)
                $day = $parts[1];
                $month = $parts[2];
                $year = (int) $parts[3] + 2000;
                $dateObject = DateTime::createFromFormat('d-m-Y', "$day-$month-$year", $timezone);
            } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dateString, $parts)) {
                // Формат: dd-mm-YYYY
                $day = $parts[1];
                $month = $parts[2];
                $year = $parts[3];
                $dateObject = DateTime::createFromFormat('d-m-Y', "$day-$month-$year", $timezone);
            } elseif (preg_match('/^(\d{2})-(\d{2}) (\d{2}):(\d{2})$/', $dateString, $parts)) {
                // Формат: dd-mm hh:mm (подставляем текущий год)
                $day = $parts[1];
                $month = $parts[2];
                $year = $now->format('Y');
                $hour = $parts[3];
                $minute = $parts[4];
                $dateObject = DateTime::createFromFormat('d-m-Y H:i', "$day-$month-$year $hour:$minute", $timezone);
            } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/', $dateString, $parts)) {
                // Формат: dd-mm-YY hh:mm
                $day = $parts[1];
                $month = $parts[2];
                $year = (int) $parts[3] + 2000;
                $hour = $parts[4];
                $minute = $parts[5];
                $dateObject = DateTime::createFromFormat('d-m-Y H:i', "$day-$month-$year $hour:$minute", $timezone);
            } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2})$/', $dateString, $parts)) {
                // Формат: dd-mm-YYYY hh:mm
                $day = $parts[1];
                $month = $parts[2];
                $year = $parts[3];
                $hour = $parts[4];
                $minute = $parts[5];
                $dateObject = DateTime::createFromFormat('d-m-Y H:i', "$day-$month-$year $hour:$minute", $timezone);
            }

            if (!empty($dateObject)) {
                return [
                    'status' => 'success',
                    'date' => $dateObject->format('Y-m-d\TH:i:sP') // ISO 8601
                ];
            }

            return [
                'status' => 'error',
                'message' => "❌ *Неверный формат даты:* `{$dateString}`\n\nИспользуйте:\n🔹 `#дд-мм`\n🔹 `#дд-мм-ГГ`\n🔹 `#дд-мм-ГГГГ`\n🔹 `#дд-мм чч:мм`\n🔹 `#дд-мм-ГГ чч:мм`\n🔹 `#дд-мм-ГГГГ чч:мм`"
            ];
        }


//        public static function findDateInText($text): array
//        {
//            // Ищем дату в разных форматах (dd-mm-YYYY, dd-mm-YYYY hh:mm, hh:mm)
//            preg_match('/#(\d{2}-\d{2}-\d{4}(?: \d{2}:\d{2})?|\d{2}:\d{2})/', $text, $dateMatch);
//
//            $dateString = $dateMatch[1] ?? null;
//
//            try {
//
//                $convertedDate = null;
//
//                if ($dateString) {
//                    $now = new DateTime(); // Текущее время
//                    $timezone = $now->getTimezone(); // Часовой пояс пользователя
//
//                    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dateString, $parts)) {
//                        // Формат: dd-mm-YYYY
//                        $day = $parts[1];
//                        $month = $parts[2];
//                        $year = $parts[3];
//                        $dateObject = DateTime::createFromFormat('d-m-Y', "$day-$month-$year", $timezone);
//                    } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2})$/', $dateString, $parts)) {
//                        // Формат: dd-mm-YYYY hh:mm
//                        $day = $parts[1];
//                        $month = $parts[2];
//                        $year = $parts[3];
//                        $hour = $parts[4];
//                        $minute = $parts[5];
//                        $dateObject = DateTime::createFromFormat('d-m-Y H:i', "$day-$month-$year $hour:$minute", $timezone);
//                    } elseif (preg_match('/^(\d{2}):(\d{2})$/', $dateString, $parts)) {
//                        // Формат: hh:mm (текущая дата)
//                        $hour = $parts[1];
//                        $minute = $parts[2];
//                        // return [$hour, $minute];
//                        $dateObject = new DateTime("today $hour:$minute", $timezone);
//                    }
//
//                    // Если дата успешно распарсилась, преобразуем в нужный формат
//                    if (!empty($dateObject)) {
//                        $convertedDate = $dateObject->format('Y-m-d\TH:i:sP'); // Преобразуем в ISO 8601
//                    } else {
//                        return [
//                            'status' => 'error',
//                            'message' => "❌ *Неверный формат даты:* `{$dateString}`\n\nИспользуйте:\n🔹 `#дд-мм-ГГГГ`\n🔹 `#дд-мм-ГГГГ чч:мм`\n🔹 `#чч:мм`"
//                        ];
//                    }
//                }
//
//                // Если дата найдена, добавляем в результат
//                return [
//                    'status' => 'success',
//                    'date' => $convertedDate ?? ''
//                ];
//            }catch (\Exception $exception){
//                return [
//                    'status' => 'error',
//                    'message' => "❌ *Неверный формат даты:* `{$dateString}`\n\nИспользуйте:\n🔹 `#дд-мм-ГГГГ`\n🔹 `#дд-мм-ГГГГ чч:мм`\n🔹 `#чч:мм`"
//                ];
//            }
//
//        }

        public function getFileUrl($fileId)
        {
            $botToken = TG_BOT_API_KEY; // Замените на токен своего бота
            $apiUrl = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";

            $response = file_get_contents($apiUrl);
            $result = json_decode($response, true);
            if (!empty($result['result']['file_path'])) {
                $filePath = $result['result']['file_path'];
                return "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
            }

            return false;
        }

        public function setUserData( $arSettings )
        {
            $mysqli = self::getDBInstance();
            $tableName = DB_TG_TABLE_NAME;
            $domain = $arSettings['domain'];
            $connection_key = $arSettings['connection_key'];
            $telegram_user_id = $arSettings['telegram_user_id'];

            return (boolean)$mysqli->query("INSERT INTO `$tableName` (`domain`, `connection_key`, `telegram_user_id`) VALUES ('$domain', '$connection_key','$telegram_user_id')");
        }

        public function deleteRowById($id): bool
        {
            $mysqli = self::getDBInstance();
            $tableName = DB_TG_TABLE_NAME;

            $id = (int) $id;

            $query = "DELETE FROM `$tableName` WHERE `id` = $id";

            return (boolean) $mysqli->query($query);
        }

        public function updateRowField($id, $field, $value): bool
        {
            $mysqli = self::getDBInstance();
            $tableName = DB_TG_TABLE_NAME;

            $field = $mysqli->real_escape_string($field);
            $value = $mysqli->real_escape_string($value);

            $query = "UPDATE `$tableName` SET `$field` = '$value' WHERE `id` = $id";

            return (boolean) $mysqli->query($query);
        }

        public function getUserInDb( string | int $user_id): array
        {
            $return = [];
            $mysqli = self::getDBInstance();
            $tableName = DB_TG_TABLE_NAME;

            foreach ($mysqli->query("SELECT * FROM $tableName WHERE telegram_user_id='{$user_id}'") as $row) {
                $return = $row;
            }

            return $return;
        }

        protected static function getSettingData($domain): array
        {
            $return = [];
            $mysqli = self::getDBInstance();
            $tableName = DB_TG_TABLE_NAME;

            foreach ($mysqli->query("SELECT * FROM $tableName WHERE domain='{$domain}'") as $row) {
                $return = $row;
            }

            //$return = static::expandData(file_get_contents(__DIR__ . '/settings.json'));

            if (defined("C_REST_CLIENT_ID") && !empty(C_REST_CLIENT_ID)) {
                $return['C_REST_CLIENT_ID'] = C_REST_CLIENT_ID;
            }
            if (defined("C_REST_CLIENT_SECRET") && !empty(C_REST_CLIENT_SECRET)) {
                $return['C_REST_CLIENT_SECRET'] = C_REST_CLIENT_SECRET;
            }

            return $return;
        }

        public function isValidBindingKey($text) {
            // Проверяем, что строка состоит только из латинских букв и цифр, и имеет длину от 24 до 32 символов
            return preg_match('/^[a-zA-Z0-9]{24,32}$/', $text);
        }

        public function containsCodeOrSql($text) {
            // Ищем ключевые слова SQL и PHP, которые не должны встречаться в ключе
            $badPatterns = [
                '/\bSELECT\b/i', '/\bINSERT\b/i', '/\bUPDATE\b/i', '/\bDELETE\b/i', '/\bDROP\b/i',
                '/\bUNION\b/i', '/\bFROM\b/i', '/\bWHERE\b/i', '/\bEXEC\b/i', '/\bSCRIPT\b/i',
                '/<\?php/i', '/\?>/i', '/system\(/i', '/exec\(/i', '/shell_exec\(/i'
            ];

            foreach ($badPatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    return true; // Найдено что-то подозрительное
                }
            }

            return false;
        }

        public static function callCurl($methodType,$url,$params,$headers)
		{
			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => $methodType,
				CURLOPT_POSTFIELDS => $params,
				CURLOPT_HTTPHEADER => $headers
			));

			$response = curl_exec($curl);

			curl_close($curl);
			return $response;
		}

		public static function addLogs($result, $comment, $fileName)
		{
			$html = '\-------'.$comment."---------\n";
			$html.= print_r($result,true);
			$html.="\n".date("d.m.Y H:i:s")."\n--------------------\n\n\n";
			$file="/home/bitrix/ext_www/marketplace.o2it.ru/telegram.tasks24/logs/$fileName.txt";
			$old_data=file_get_contents($file);
			file_put_contents($file, $html.$old_data);
		}

	}