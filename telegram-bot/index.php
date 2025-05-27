<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

ini_set('log_errors', 1);
ini_set('error_log', '/home/bitrix/ext_www/marketplace.o2it.ru/telegram.tasks24/logs/file_error.log');

use Telegram\Bot\Api;
use Predis\Client as RedisClient;

require_once __DIR__ . '../../src/settings.php';
require_once __DIR__ . '../../src/crest.php';
require_once __DIR__ . '../../src/telegram_bot_service.php';
require_once  __DIR__ . '../../src/redice_service.php';
require __DIR__ . '../../src/vendor/autoload.php';

$input_data_json = file_get_contents("php://input");
$input_data = json_decode($input_data_json,1);

if($input_data)
{
    $botApiService = new TelegramBotService(TG_BOT_API_KEY);
    $botApiService::addLogs( [$input_data], "update", "tg_updates");
    $telegram = new Api(TG_BOT_API_KEY);
    $update = $telegram->commandsHandler(true);


    $update = $telegram->getWebhookUpdate();
    if (isset($update['callback_query']))
    {
        $redis = new RedisService();
        $callback_query = $update['callback_query'];
        $callback_data = $callback_query['data']; // "unbind_yes" или "unbind_no"
        $callback_chat_id = $callback_query['message']['chat']['id'];
        $callback_message_id = $callback_query['message']['message_id'];

        // Проверяем в Redis, есть ли активный запрос на отвязку
        $stored_state = $redis->get("unbind:$callback_chat_id");
        $user_record = $botApiService->getUserInDb($callback_chat_id);
        $domain = $user_record["domain"];
        $connection_key = $user_record["connection_key"];

        if ($stored_state === "waiting_confirmation") {
            if ($callback_data == "unbind_yes") {
                $unbind_res = $botApiService->deleteRowById($user_record["id"]);
                //$unbind_res = $botApiService->updateRowField($user_record["id"], "connection_key", "" );
                if($unbind_res)
                {
                    $telegram->sendMessage([
                        'chat_id' => $callback_chat_id,
                        'text' => "🔓 Ключ успешно отвязан!\nВведите домен вашего Битрикс24 портала (например, bitrix24.company.ru)."
                    ]);
                    $redis->delete(key:"unbind:$callback_chat_id");
                    $redis->set(key: "waiting_for_domain:$callback_chat_id", value: "null");

                    $waitingDomain = $redis->get(key: "waiting_for_domain:$callback_chat_id");
                    $waitingCntKey = $redis->get(key: "waiting_for_connection_key:$callback_chat_id");
                    /*
                    */
                    // $telegram->sendMessage([
                    //    'chat_id' => $callback_chat_id,
                    //    'text' => "<pre>" . htmlspecialchars(json_encode([$waitingDomain, $waitingCntKey], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>",
                    //    'parse_mode' => 'HTML',
                    //]);
                    return;
                }else{
                    $telegram->sendMessage([
                        'chat_id' => $callback_chat_id,
                        'text' => "Что-то пошло не так"
                    ]);
                }
            } elseif ($callback_data == "unbind_no"){
                $redis->delete(key:"unbind:$callback_chat_id"); // Удаляем состояние

                $telegram->sendMessage([
                    'chat_id' => $callback_chat_id,
                    'text' => "⛔ Отмена действия."
                ]);
            }
        } else {
            // Если нет сохраненного состояния, игнорируем нажатие
            $telegram->sendMessage([
                'chat_id' => $callback_chat_id,
                'text' => "❌ Ошибка: Нет активного запроса на отвязку."
            ]);
        }
    }
    else if ($update->has('message'))
    {
        $redis = new RedisService();
        $message = $update->getMessage();
        $chat_id = $message->getChat()->getId();
        $text = $message->getText();
        $caption = $message->getCaption();
        $userId = $update->getMessage()->getFrom()->getId();
        $username = $message->getFrom()->getUsername() ?? 'NoUsername';

        $user_record = $botApiService->getUserInDb($userId);
        $botApiService::addLogs( [$message, $chat_id, $text, $caption, $username, $user_record], "update", "tg_updates");

        if(
            !empty($user_record)
            && !empty($user_record["connection_key"])
            && $user_record["connection_key"] != "null"
            && !empty($user_record["domain"]) )
        {
            $domain = $user_record["domain"];
            $connection_key = $user_record["connection_key"];
            $botApiService::addLogs( $message, "message", "errors");

            if($text == "/unbind_key") // отвязка ключа
            {
                try {
                    $redis->set(key: "unbind:$chat_id", value: "waiting_confirmation", ttl: 300); // 5 минут хранения

                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => "✅ Да", 'callback_data' => "unbind_yes"],
                                ['text' => "❌ Нет", 'callback_data' => "unbind_no"]
                            ]
                        ]
                    ];

                    $telegram->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Вы уверены, что хотите отвязать ключ?",
                        'reply_markup' => json_encode($keyboard)
                    ]);

                    // $telegram->sendMessage([
                    //   'chat_id' => $chat_id,
                    //    'text' => "<pre>" . htmlspecialchars(json_encode($user_record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>",
                    //    'parse_mode' => 'HTML',
                    // ]);
                    // return;
                } catch (\Exception $exception) {
                    $botApiService::addLogs($exception, "error", "errors");
                    $telegram->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Произошла ошибка, пожалуйста попробуйте еще раз."
                    ]);
                }
                return;
            }
            else
            {
                try {
                    $result = $botApiService->processMessage($message);
                    $botApiService::addLogs( $result, "result", "errors");
                    if ($result['status'] === 'error') {
                        $telegram->sendMessage([
                            'chat_id' => $chat_id,
                            'text' => $result['message']
                        ]);
                    } else {

                        $taskTitle = $result['title'];
                        $taskDescription = $result['description'];
                        $taskDeadline = $result['deadline'];

                        $connection_records = CRest::call("app.option.get", [ "option" => "connection_records" ], $domain);

                        $send_message = [
                            "chat_id" => $chat_id,
                        ];
                        if(!empty($connection_records["result"]) && count($connection_records["result"]) > 0) {
                            $connection_record = array_filter($connection_records["result"], function ($record) use ($connection_key) {
                                return $record['key'] === $connection_key;
                            });
                            $connection_record = array_values($connection_record)[0];
                            if(empty($connection_record))
                            {

                                $telegram->sendMessage([
                                    'chat_id' => $chat_id,
                                    'text' => "Ключ привязки был удалён. Пожалуйста отвяжите ключ и привяжите заново, для корректной работы.",
                                ]);
                                return;
                            }
                            $bx_user_id = $connection_record["employee_id"];
                            $file_url = $result["documentUrl"] ?? $result["photoUrl"] ?? null;
                            $taskAttacheFile = [];
                            if($file_url)
                            {
                                $path_info = pathinfo(parse_url($file_url, PHP_URL_PATH));
                                $file_extension = isset($path_info['extension']) ? $path_info['extension'] : 'unknown';


                                $disk_id = CRest::getMyDiskId($bx_user_id, $domain);
                                $folder_id = CRest::getUploadedFilesFolderId($disk_id, $domain);
                                $file_base64 = CRest::getFileBase64($file_url);
                                $timestamp = date('Ymd_His');
                                $fileName = "file_{$timestamp}.$file_extension";
                                $connection_record["fileName"] = $fileName;
                                $uploadFileToDisk = CRest::uploadFileToDisk($folder_id, $fileName, $file_base64, $domain);
                                if($uploadFileToDisk)
                                {
                                    $taskAttacheFile[] = "n$uploadFileToDisk";
                                }
                            }
                            $botApiService::addLogs( $connection_record, "params", "errors");

                            $params = [
                                "TITLE" => $taskTitle,
                                "DESCRIPTION" => $taskDescription,
                                "CREATED_BY" => $bx_user_id,
                                "RESPONSIBLE_ID" => $bx_user_id,
                                "TAGS" => "Telegram",
                                "UF_TASK_WEBDAV_FILES" => $taskAttacheFile
                            ];
                            $taskDeadline ? $params["DEADLINE"] = $taskDeadline : null;

                            $botApiService::addLogs( $params, "params", "errors");
                            $create_task = CRest::call("tasks.task.add", [ "fields" => $params ], $domain );
                            // $create_task = CRest::call("task.item.add", [ $params ], $domain );
                            if($create_task["result"]["task"] )
                            {
                                $task_id = $create_task["result"]["task"]["id"];
                                $send_message['text'] = "✅ Задача создана:\n\n📌 <a href=\"https://$domain/company/personal/user/$bx_user_id/tasks/task/view/$task_id/\">#{$taskTitle}</a> \n\n📝 {$taskDescription}";
                                $send_message['parse_mode'] = 'HTML';

                                // $update_task = CRest::call("tasks.task.update", [ "fields" => [ "TAGS" => "Telegram" ] ], $domain );
                                // if($update_task["result"] )
                                //if($update_task["result"] )
                                // {
                                    /* 'text' => `✅ Задача создана:\n\n📌 *{$taskTitle}*\ n\n📝 {$taskDescription} \n\n [Ссылка на задачу](https://$domain/company/personal/user/$bx_user_id/tasks/task/view/1/) `,
                                    'parse_mode' => 'Markdown', */
                                // }else{
                                    // $botApiService::addLogs( $create_task, "update_task", "errors");
                                // }
                            }
                            else
                            {
                                $botApiService::addLogs( $create_task, "create_task", "errors");
                                $send_message['text'] = "Произошла ошибка, пожалуйста попробуйте еще раз.";
                            }

                        }

                        $botApiService::addLogs( $connection_records, "result", "errors");
                        $telegram->sendMessage($send_message);

                        // Здесь вызови свою функцию создания задачи в Битрикс24
                        // createTask($taskTitle, $taskDescription);
                    }

                } catch (\Throwable $th) {
                    $botApiService::addLogs( $th, "error", "errors");
                    $telegram->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Произошла ошибка, пожалуйста попробуйте еще раз."
                    ]);
                }
            }
        }else if(!empty($user_record) && $user_record["connection_key"] == null)
        {
            $domain = $user_record["domain"];
            $connection_key = $text;
            try {
                $auth_tokens = CRest::getSettingData($domain);
                if(!empty($auth_tokens["access_token"]) && !empty($auth_tokens["refresh_token"]))
                {
                    $connection_records = CRest::call("app.option.get", [ "option" => "connection_records" ], $domain);
                    if(!empty($connection_records["result"]) && count($connection_records["result"]) > 0)
                    {
                        $filtered = array_filter($connection_records["result"], function ($record) use ($connection_key) {
                            return $record['key'] === $connection_key;
                        });
                        if(!empty($filtered))
                        {
                            $set_response = $botApiService->updateRowField($user_record["id"], "connection_key", $connection_key);
                            // $set_response = $botApiService->deleteRowById($user_record["id"]);
                            if($set_response)
                            {
                                $redis->set(key: "waiting_for_connection_key:$chat_id", value: $connection_key);
                                $telegram->sendMessage([
                                    'chat_id' => $chat_id,
                                    'text' => "Ваши данные успешно сохранены!"
                                ]);
                            }
                            else
                            {
                              $telegram->sendMessage([
                                    'chat_id' => $chat_id,
                                    'text' => json_encode($set_response)
                                ]);
                            }
                        }
                        else
                        {
                            $botApiService::addLogs( $filtered, "connection_record", "updates");
                            $telegram->sendMessage([
                                'chat_id' => $chat_id,
                                'text' => "Не удалось найти подключение по ключу. Пожалуйста попробуйте ввести корректный ключ."
                            ]);
                        }
                    }
                }
            } catch (\Throwable $th) {
                $botApiService::addLogs( $th, "error", "errors");
            }
        }else{
            $waitingDomain = $redis->get(key: "waiting_for_domain:$chat_id");
            $waitingCntKey = $redis->get(key: "waiting_for_connection_key:$chat_id");

            if ($text == "/start" || $text == "привет")
            {
                /*
                    if($waitingDomain || ( $waitingCntKey && $waitingCntKey !== "null"))
                    {
                        $redis->set(key: "waiting_for_domain:$chat_id", value: "null");
                    }
                */
                $redis->set(key: "waiting_for_domain:$chat_id", value: "null");
                $telegram->sendMessage([
                    'chat_id' => $chat_id,
                    'text' => "Вас приветствует Бот для создания задач в Битрикс24!\nВведите домен вашего Битрикс24 портала (например, bitrix24.company.ru)."
                ]);
                return;
            }

            $botApiService::addLogs( [$waitingDomain, $waitingCntKey], "update", "tg_updates");

            if($waitingDomain && $waitingDomain == "null") {
                if (preg_match('/^[a-zA-Z0-9-]+\.[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/', $text))
                {
                    $redis->set("waiting_for_domain:$chat_id", $text);
                    $redis->set("waiting_for_connection_key:$chat_id", "null");

                    $telegram->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Вы ввели домен: $text. Теперь введите ваш `Ключ привязки`.\n\nЕго можно найти на вашем портале в интерфейсе приложения \"Постановка задач из telegram бота\"."
                    ]);
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Пожалуйста, введите корректный домен. Пример: bitrix24.company.ru."
                    ]);
                }
            }else if($waitingCntKey && $waitingCntKey == "null")
            {
                if ($botApiService->isValidBindingKey($text) && !$botApiService->containsCodeOrSql($text))
                {
                    $connection_key = $text;
                    $domain = $redis->get(key: "waiting_for_domain:$chat_id");
                    try {
                        $auth_tokens = CRest::getSettingData($domain);
                        if(!empty($auth_tokens["access_token"]) && !empty($auth_tokens["refresh_token"]))
                        {
                            $connection_records = CRest::call("app.option.get", [ "option" => "connection_records" ], $domain);
                            if(!empty($connection_records["result"]) && count($connection_records["result"]) > 0)
                            {
                                $filtered = array_filter($connection_records["result"], function ($record) use ($connection_key) {
                                    return $record['key'] === $connection_key;
                                });
                                if(!empty($filtered))
                                {
                                    $botApiService::addLogs( $filtered, "connection_record", "updates");
                                    $arFields = [
                                        "domain" => $domain,
                                        "connection_key" => $connection_key,
                                        "telegram_user_id" => $userId,
                                    ];
                                    $set_response = $botApiService->setUserData($arFields);
                                    if($set_response)
                                    {
                                        $redis->set(key: "waiting_for_connection_key:$chat_id", value: $connection_key);
                                        $telegram->sendMessage([
                                            'chat_id' => $chat_id,
                                            'text' => "Ваши данные успешно сохранены!"
                                        ]);
                                        $redis->delete(key: "waiting_for_domain:$chat_id");
                                        $redis->delete(key: "waiting_for_connection_key:$chat_id");
                                    }
                                    else
                                    {
//                                        $redis->set(key: "waiting_for_connection_key:$chat_id", value: $connection_key);
                                        $telegram->sendMessage([
                                            'chat_id' => $chat_id,
                                            'text' => json_encode($set_response)
                                        ]);
                                    }
                                }
                                else
                                {
                                    $botApiService::addLogs( $filtered, "connection_record", "updates");
                                    $telegram->sendMessage([
                                        'chat_id' => $chat_id,
                                        'text' => "Не удалось найти подключение по ключу. Пожалуйста попробуйте ввести корректный ключ."
                                    ]);
                                }
                            }else{
                                //$telegram->sendMessage([
                                //  'chat_id' => $chat_id,
                                //    'text' => "<pre>" . htmlspecialchars(json_encode($connection_records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>",
                                //    'parse_mode' => 'HTML',
                                //]);
                                return;
                                $telegram->sendMessage([
                                    'chat_id' => $chat_id,
                                    'text' => "Ключ привязки не найден, пожалуйста введите корректные данные!"
                                ]);
                            }
                        }else{
                            $telegram->sendMessage([
                                'chat_id' => $chat_id,
                                'text' => "Неправильно введены данные! Попробуйте заново. /start"
                            ]);
                        }
                    } catch (\Throwable $th) {
                        $botApiService::addLogs( $th, "error", "errors");
                    }

                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chat_id,
                        'text' => "Ошибка! Введённый текст не похож на ключ привязки."
                    ]);
                }
            }
        }
    }
}