<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class Badge extends Component
{
    public const VARIANT_NEUTRAL = 'neutral';
    public const VARIANT_SUCCESS = 'success';
    public const VARIANT_WARNING = 'warning';
    public const VARIANT_DANGER = 'danger';
    public const VARIANT_INFO = 'info';

    public static function render(string $label, string $variant = self::VARIANT_NEUTRAL): void
    {
        $allowed = [
            self::VARIANT_NEUTRAL,
            self::VARIANT_SUCCESS,
            self::VARIANT_WARNING,
            self::VARIANT_DANGER,
            self::VARIANT_INFO,
        ];

        if (!in_array($variant, $allowed, true)) {
            $variant = self::VARIANT_NEUTRAL;
        }

        printf(
            '<span class="%s">%s</span>',
            esc_attr(self::classes(['mcms-badge', 'mcms-badge--' . $variant])),
            esc_html($label)
        );
    }
}
