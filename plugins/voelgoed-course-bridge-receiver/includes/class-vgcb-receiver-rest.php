<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class VGCB_Receiver_Rest
{
    public function __construct(
        private readonly VGCB_Receiver_Authenticator $authenticator,
        private readonly VGCB_Receiver_Access $access
    ) {
    }

    public function hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('voelgoed-course-bridge/v1', '/grant-access', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_request'],
            'permission_callback' => [$this, 'permission_callback'],
        ]);
    }

    public function permission_callback(WP_REST_Request $request): true|WP_Error
    {
        return $this->authenticator->authenticate($request);
    }

    public function handle_request(WP_REST_Request $request): WP_REST_Response
    {
        $raw_body = $request->get_body();
        $payload = json_decode($raw_body, true);

        if (!is_array($payload)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid JSON payload.',
            ], 400);
        }

        $result = $this->access->process_payload($payload);
        $status = isset($result['status']) ? absint($result['status']) : 200;
        unset($result['status']);

        return new WP_REST_Response($result, $status);
    }
}
