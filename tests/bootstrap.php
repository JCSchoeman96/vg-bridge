<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('VGCB_SENDER_VERSION')) {
    define('VGCB_SENDER_VERSION', 'test');
}

if (!defined('VGCB_RECEIVER_VERSION')) {
    define('VGCB_RECEIVER_VERSION', 'test');
}

if (!defined('VGCB_SENDER_PLUGIN_DIR')) {
    define('VGCB_SENDER_PLUGIN_DIR', dirname(__DIR__) . '/plugins/voelgoed-course-bridge-sender/');
}

if (!defined('VGCB_RECEIVER_PLUGIN_DIR')) {
    define('VGCB_RECEIVER_PLUGIN_DIR', dirname(__DIR__) . '/plugins/voelgoed-course-bridge-receiver/');
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

require_once __DIR__ . '/Support/FakeWooCommerce.php';

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('absint')) {
    function absint(mixed $maybeint): int
    {
        return abs((int) $maybeint);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key) ?? '');
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user(string $username, bool $strict = false): string
    {
        $username = preg_replace('/\s+/', '', $username) ?? '';

        return $strict ? preg_replace('/[^a-z0-9_\-]/', '', strtolower($username)) ?? '' : $username;
    }
}

if (!function_exists('is_email')) {
    function is_email(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE, $depth);
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $str): string
    {
        return strip_tags($str);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('get_role')) {
    function get_role(string $role): ?object
    {
        $roles = $GLOBALS['vgcb_test_roles'] ?? ['customer' => true, 'subscriber' => true];

        return isset($roles[$role]) ? (object) ['name' => $role] : null;
    }
}

if (!function_exists('username_exists')) {
    function username_exists(string $username): bool
    {
        foreach ($GLOBALS['vgcb_test_users'] ?? [] as $user) {
            if (($user['user_login'] ?? '') === $username) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('network_site_url')) {
    function network_site_url(string $path = '', ?string $scheme = null): string
    {
        return 'https://leer.voelgoed.co.za/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;
        private array $data;

        public function __construct(string $code = '', string $message = '', array $data = [])
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data(): array
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /**
         * @param array<string, string> $headers
         */
        public function __construct(
            private string $body = '',
            private array $headers = []
        ) {
        }

        public function get_body(): string
        {
            return $this->body;
        }

        public function get_header(string $name): ?string
        {
            return $this->headers[strtolower($name)] ?? null;
        }

        public function with_header(string $name, string $value): self
        {
            $clone = clone $this;
            $clone->headers[strtolower($name)] = $value;

            return $clone;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(
            public mixed $data = null,
            public int $status = 200
        ) {
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const CREATABLE = 'POST';
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public int $ID;
        public string $user_email;
        public string $user_login;
        public string $display_name;

        public function __construct(int $id = 0, string $email = 'test@example.com', string $login = 'test')
        {
            $this->ID = $id;
            $this->user_email = $email;
            $this->user_login = $login;
            $this->display_name = $login;
        }
    }
}

require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-activator.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-activator.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-outbox.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-http.php';
require_once VGCB_SENDER_PLUGIN_DIR . 'includes/class-vgcb-sender-order-handler.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-log.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-mailer.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-payload-validator.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-access.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-authenticator.php';
require_once VGCB_RECEIVER_PLUGIN_DIR . 'includes/class-vgcb-receiver-rest.php';
