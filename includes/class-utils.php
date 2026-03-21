<?php
if (!defined('ABSPATH')) exit;

class WABE_Utils
{
    public static function wabe_maybe_base64_decode($value)
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }
        $decoded = base64_decode($value, true);
        return ($decoded !== false) ? $decoded : $value;
    }
}
