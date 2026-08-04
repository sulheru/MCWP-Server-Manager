<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OptiGrid_Subscriptions_Subscription_Manager_Controller
{
    private const PAGE_SLUG = 'optigrid-subscriptions-manage';
    private const ACTION_SAVE = 'optigrid_subscriptions_save_subscription';
    private const NONCE_ACTION = 'optigrid_subscriptions_edit_subscription';
    private const NONCE_NAME = 'optigrid_subscriptions_subscription_nonce';

    private OptiGrid_Subscriptions_Subscription_Admin_Repository $repository;
    private OptiGrid_Subscriptions_Subscription_Admin_Service $service;

    public function __construct(
        OptiGrid_Subscriptions_Subscription_Admin_Repository $repository,
        OptiGrid_Subscriptions_Subscription_Admin_Service $service
    ) {
        $this->repository = $repository;
        $this->service = $service;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu'], 41);
        add_action('admin_post_' . self::ACTION_SAVE, [$this, 'handle_save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu(): void
    {
        add_submenu_page(
            'gestor-mc-srv',
            __('Gestionar suscripciones', 'optigrid-subscriptions'),
            __('Gestionar suscripciones', 'optigrid-subscriptions'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function enqueue_assets(): void
    {
        if (!$this->is_current_page()) {
            return;
        }

        wp_enqueue_style(
            'optigrid-subscriptions-admin',
            OPTIGRID_SUBSCRIPTIONS_URL . 'assets/css/admin.css',
            [],
            OPTIGRID_SUBSCRIPTIONS_VERSION
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes.', 'optigrid-subscriptions'));
        }

        $subscription_id = isset($_GET['subscription_id'])
            ? absint(wp_unslash($_GET['subscription_id']))
            : 0;

        if ($subscription_id > 0) {
            $this->render_edit($subscription_id);
            return;
        }

        $this->render_list();
    }

    public function handle_save(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos suficientes.', 'optigrid-subscriptions'));
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        $subscription_id = isset($_POST['subscription_id'])
            ? absint(wp_unslash($_POST['subscription_id']))
            : 0;

        try {
            $this->service->update(
                $subscription_id,
                [
                    'status' => $_POST['status'] ?? '',
                    'starts_at' => $_POST['starts_at'] ?? '',
                    'ends_at' => $_POST['ends_at'] ?? '',
                    'cancellation_reason' => $_POST['cancellation_reason'] ?? '',
                ]
            );

            $status = 'saved';
        } catch (Throwable $exception) {
            $status = 'error';
            set_transient(
                'optigrid_subscriptions_admin_error_' . get_current_user_id(),
                $exception->getMessage(),
                60
            );
        }

        $url = add_query_arg(
            [
                'page' => self::PAGE_SLUG,
                'subscription_id' => $subscription_id,
                'subscription_updated' => $status,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    private function render_list(): void
    {
        $filters = [
            'status' => isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '',
            'plan_id' => isset($_GET['plan_id']) ? absint(wp_unslash($_GET['plan_id'])) : 0,
            'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        ];

        $page = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
        $result = $this->repository->search($filters, $page, 20);
        $plans = $this->repository->all_plans();

        require OPTIGRID_SUBSCRIPTIONS_DIR . 'templates/admin/subscriptions-list.php';
    }

    private function render_edit(int $subscription_id): void
    {
        $subscription = $this->repository->find($subscription_id);

        if ($subscription === null) {
            wp_die(esc_html__('La suscripción no existe.', 'optigrid-subscriptions'));
        }

        $message = isset($_GET['subscription_updated'])
            ? sanitize_key(wp_unslash($_GET['subscription_updated']))
            : '';

        $error = '';
        if ($message === 'error') {
            $error = (string) get_transient(
                'optigrid_subscriptions_admin_error_' . get_current_user_id()
            );
            delete_transient(
                'optigrid_subscriptions_admin_error_' . get_current_user_id()
            );
        }

        require OPTIGRID_SUBSCRIPTIONS_DIR . 'templates/admin/subscription-edit.php';
    }

    private function is_current_page(): bool
    {
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        return $page === self::PAGE_SLUG;
    }

    public static function action_save(): string
    {
        return self::ACTION_SAVE;
    }

    public static function nonce_action(): string
    {
        return self::NONCE_ACTION;
    }

    public static function nonce_name(): string
    {
        return self::NONCE_NAME;
    }
}
