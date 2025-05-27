<?php

require_once __DIR__ . '/settings.php';
//require 'vendor/autoload.php';

class RedisService
{
    private static ?Redis $redis = null;

    public function __construct()
    {
        if (self::$redis === null) {
            self::$redis = new Redis();
            self::$redis->connect(REDIS_HOST, REDIS_PORT);
            if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
                self::$redis->auth(REDIS_PASSWORD);
            }
        }
    }

    /**
     * Сохранение данных в Redis
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl Время жизни в секундах (по умолчанию нет)
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $value = is_array($value) ? json_encode($value) : $value;
        return $ttl ? self::$redis->setex($key, $ttl, $value) : self::$redis->set($key, $value);
    }

    /**
     * Получение данных из Redis
     *
     * @param string $key
     * @param bool $asArray Если true, декодирует JSON в массив
     * @return mixed
     */
    public function get(string $key, bool $asArray = false): mixed
    {
        $value = self::$redis->get($key);
        return ($asArray && $value) ? json_decode($value, true) : $value;
    }

    /**
     * Удаление ключа из Redis
     *
     * @param string $key
     * @return int Количество удаленных ключей
     */
    public function delete(string $key): int
    {
        return self::$redis->del($key);
    }

    /**
     * Проверка существования ключа
     *
     * @param string $key
     * @return bool
     */
    public function exists(string $key): bool
    {
        return self::$redis->exists($key) > 0;
    }
}
