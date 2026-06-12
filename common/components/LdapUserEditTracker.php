<?php

namespace common\components;

use Yii;

/**
 * เก็บข้อมูลผู้แก้ไขล่าสุดของ LDAP user (ใช้แสดงใน page subtitle)
 */
class LdapUserEditTracker
{
    private static function filePath(): string
    {
        return Yii::getAlias('@runtime/ldap_user_last_edit.json');
    }

    /**
     * @return array{editor?:string,editedAt?:string}|null
     */
    public static function get(string $cn): ?array
    {
        $cn = trim($cn);
        if ($cn === '') {
            return null;
        }

        $all = self::readAll();
        $entry = $all[$cn] ?? null;
        return is_array($entry) ? $entry : null;
    }

    public static function record(string $cn, string $editor, ?string $editedAt = null): void
    {
        $cn = trim($cn);
        $editor = trim($editor);
        if ($cn === '' || $editor === '') {
            return;
        }

        $all = self::readAll();
        $all[$cn] = [
            'editor' => $editor,
            'editedAt' => $editedAt ?? date('d/m/Y H:i:s'),
        ];
        self::writeAll($all);
    }

    public static function formatWhenChanged(?string $whenChanged): string
    {
        $whenChanged = trim((string)$whenChanged);
        if ($whenChanged === '') {
            return '';
        }

        if (!preg_match('/(\d{8})(\d{6})/', $whenChanged, $m)) {
            $digits = preg_replace('/[^0-9]/', '', $whenChanged);
            if (!preg_match('/(\d{8})(\d{6})/', $digits, $m)) {
                return '';
            }
        }

        $dt = \DateTime::createFromFormat('YmdHis', $m[1] . $m[2], new \DateTimeZone('UTC'));
        if ($dt === false) {
            return '';
        }

        $dt->setTimezone(new \DateTimeZone('Asia/Bangkok'));
        return $dt->format('d/m/Y H:i:s');
    }

    /**
     * @return array<string, array{editor?:string,editedAt?:string}>
     */
    private static function readAll(): array
    {
        $path = self::filePath();
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, array{editor?:string,editedAt?:string}> $data
     */
    private static function writeAll(array $data): void
    {
        $path = self::filePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }
}
