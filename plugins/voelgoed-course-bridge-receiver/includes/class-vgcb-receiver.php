<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver
{
    private static ?VGCB_Receiver $instance = null;

    public static function instance(): VGCB_Receiver
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        VGCB_Receiver_Activator::maybe_upgrade();

        $log = new VGCB_Receiver_Log();
        $mailer = new VGCB_Receiver_Mailer();
        $validator = new VGCB_Receiver_Payload_Validator();
        $access = new VGCB_Receiver_Access($log, $mailer, $validator);
        $authenticator = new VGCB_Receiver_Authenticator($log);

        (new VGCB_Receiver_Rest($authenticator, $access))->hooks();
        (new VGCB_Receiver_Admin($log))->hooks();
    }
}
