<?php
if (!defined('ABSPATH')) exit;

interface WABE_AI_Provider_Interface
{
    public function text($prompt, $args = []);
}
