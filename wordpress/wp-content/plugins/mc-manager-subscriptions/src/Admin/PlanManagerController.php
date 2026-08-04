<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OptiGrid_Subscriptions_Plan_Manager_Controller
{
    private const PAGE_SLUG = 'optigrid-subscription-plans';
    private const SAVE_ACTION = 'optigrid_subscriptions_save_plan';
    private const DUPLICATE_ACTION = 'optigrid_subscriptions_duplicate_plan';
    private const TOGGLE_ACTION = 'optigrid_subscriptions_toggle_plan';

    private OptiGrid_Subscriptions_Plan_Admin_Repository $plans;

    public function __construct(
        OptiGrid_Subscriptions_Plan_Admin_Repository $plans
    ) {
        $this->plans = $plans;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu'], 45);
        add_action(
            'admin_post_' . self::SAVE_ACTION,
            [$this, 'handle_save']
        );
        add_action(
            'admin_post_' . self::DUPLICATE_ACTION,
            [$this, 'handle_duplicate']
        );
        add_action(
            'admin_post_' . self::TOGGLE_ACTION,
            [$this, 'handle_toggle']
        );
        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueue_assets']
        );
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'gestor-mc-srv',
            __('Planes de suscripción', 'optigrid-subscriptions'),
            __('Planes de suscripción', 'optigrid-subscriptions'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page'],
            45
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

    public function render_page(): void
    {
        $this->require_capability();

        $plan_id = isset($_GET['plan_id'])
            ? absint(wp_unslash($_GET['plan_id']))
            : 0;

        $mode = isset($_GET['mode'])
            ? sanitize_key(wp_unslash($_GET['mode']))
            : '';

        $notice = isset($_GET['plan_notice'])
            ? sanitize_key(wp_unslash($_GET['plan_notice']))
            : '';

        $error = isset($_GET['plan_error'])
            ? sanitize_text_field(
                rawurldecode(wp_unslash($_GET['plan_error']))
            )
            : '';

        if ($mode === 'new' || $plan_id > 0) {
            $plan = $plan_id > 0 ? $this->plans->find($plan_id) : null;

            if ($plan_id > 0 && $plan === null) {
                wp_die(
                    esc_html__(
                        'El plan solicitado no existe.',
                        'optigrid-subscriptions'
                    )
                );
            }

            $usage = $plan_id > 0
                ? $this->plans->usage_counts($plan_id)
                : ['subscriptions' => 0, 'orders' => 0];

            $default_code = $plan_id > 0
                ? (string) $plan['code']
                : wp_generate_uuid4();

            require OPTIGRID_SUBSCRIPTIONS_DIR
                . 'templates/admin/plan-edit.php';

            return;
        }

        $plans = $this->plans->all();

        require OPTIGRID_SUBSCRIPTIONS_DIR
            . 'templates/admin/plans-list.php';
    }

    public function handle_save(): void
    {
        $this->require_capability();
        check_admin_referer('optigrid_subscriptions_save_plan');

        $id = isset($_POST['plan_id'])
            ? absint(wp_unslash($_POST['plan_id']))
            : 0;

        try {
            $data = $this->sanitize_plan_input($_POST);

            if ($this->plans->code_exists($data['code'], $id)) {
                throw new InvalidArgumentException(
                    'Ya existe otro plan con ese código.'
                );
            }

            $saved_id = $this->plans->save($data, $id);

            $this->redirect(
                [
                    'plan_id'     => $saved_id,
                    'plan_notice' => $id > 0 ? 'updated' : 'created',
                ]
            );
        } catch (Throwable $exception) {
            $args = [
                'mode'       => $id > 0 ? '' : 'new',
                'plan_error' => rawurlencode($exception->getMessage()),
            ];

            if ($id > 0) {
                $args['plan_id'] = $id;
            }

            $this->redirect($args);
        }
    }

    public function handle_duplicate(): void
    {
        $this->require_capability();

        $id = isset($_GET['plan_id'])
            ? absint(wp_unslash($_GET['plan_id']))
            : 0;

        check_admin_referer(
            'optigrid_subscriptions_duplicate_plan_' . $id
        );

        try {
            $new_id = $this->plans->duplicate($id);

            $this->redirect(
                [
                    'plan_id'     => $new_id,
                    'plan_notice' => 'duplicated',
                ]
            );
        } catch (Throwable $exception) {
            $this->redirect(
                [
                    'plan_error' => rawurlencode(
                        $exception->getMessage()
                    ),
                ]
            );
        }
    }

    public function handle_toggle(): void
    {
        $this->require_capability();

        $id = isset($_GET['plan_id'])
            ? absint(wp_unslash($_GET['plan_id']))
            : 0;

        $active = isset($_GET['active'])
            ? (bool) absint(wp_unslash($_GET['active']))
            : false;

        check_admin_referer(
            'optigrid_subscriptions_toggle_plan_' . $id
        );

        try {
            $this->plans->set_active($id, $active);

            $this->redirect(
                [
                    'plan_notice' => $active
                        ? 'activated'
                        : 'deactivated',
                ]
            );
        } catch (Throwable $exception) {
            $this->redirect(
                [
                    'plan_error' => rawurlencode(
                        $exception->getMessage()
                    ),
                ]
            );
        }
    }

    private function sanitize_plan_input(array $input): array
    {
        $code = isset($input['code'])
            ? trim(sanitize_text_field(wp_unslash($input['code'])))
            : '';

        $name = isset($input['name'])
            ? sanitize_text_field(wp_unslash($input['name']))
            : '';

        $description = isset($input['description'])
            ? sanitize_textarea_field(
                wp_unslash($input['description'])
            )
            : '';

        $price_raw = isset($input['price'])
            ? str_replace(
                ',',
                '.',
                sanitize_text_field(wp_unslash($input['price']))
            )
            : '0';

        $currency = isset($input['currency'])
            ? strtoupper(
                sanitize_text_field(wp_unslash($input['currency']))
            )
            : 'EUR';

        $duration_days = isset($input['duration_days'])
            ? absint(wp_unslash($input['duration_days']))
            : 0;

        $sort_order = isset($input['sort_order'])
            ? intval(wp_unslash($input['sort_order']))
            : 0;

        $is_active = isset($input['is_active']) ? 1 : 0;
        $is_visible = isset($input['is_visible']) ? 1 : 0;

        if ($code === '') {
            throw new InvalidArgumentException(
                'El código interno es obligatorio.'
            );
        }

        if (
            strlen($code) > 100
            || !preg_match('/^[A-Za-z0-9._:-]+$/', $code)
        ) {
            throw new InvalidArgumentException(
                'El código interno solo admite letras, números, punto, guion, guion bajo y dos puntos.'
            );
        }

        if ($name === '') {
            throw new InvalidArgumentException(
                'El nombre del plan es obligatorio.'
            );
        }

        if (!is_numeric($price_raw) || (float) $price_raw < 0) {
            throw new InvalidArgumentException(
                'El precio debe ser un número igual o superior a cero.'
            );
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException(
                'La moneda debe tener un código ISO de tres letras.'
            );
        }

        if ($duration_days < 1) {
            throw new InvalidArgumentException(
                'La duración debe ser de al menos un día.'
            );
        }

        if ($is_visible === 1 && $is_active !== 1) {
            throw new InvalidArgumentException(
                'Un plan visible debe estar también activo.'
            );
        }

        return [
            'code'          => $code,
            'name'          => $name,
            'description'   => $description,
            'price'         => number_format(
                (float) $price_raw,
                2,
                '.',
                ''
            ),
            'currency'      => $currency,
            'duration_days' => $duration_days,
            'is_active'     => $is_active,
            'is_visible'    => $is_visible,
            'sort_order'    => $sort_order,
        ];
    }

    private function require_capability(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__(
                    'No tienes permisos suficientes.',
                    'optigrid-subscriptions'
                )
            );
        }
    }

    private function is_current_page(): bool
    {
        $page = isset($_GET['page'])
            ? sanitize_key(wp_unslash($_GET['page']))
            : '';

        return $page === self::PAGE_SLUG;
    }

    private function redirect(array $args = []): void
    {
        $url = add_query_arg(
            array_merge(
                ['page' => self::PAGE_SLUG],
                $args
            ),
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    public static function page_slug(): string
    {
        return self::PAGE_SLUG;
    }

    public static function save_action(): string
    {
        return self::SAVE_ACTION;
    }

    public static function duplicate_action(): string
    {
        return self::DUPLICATE_ACTION;
    }

    public static function toggle_action(): string
    {
        return self::TOGGLE_ACTION;
    }
}
