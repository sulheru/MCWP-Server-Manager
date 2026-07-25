<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class Button extends Component
{
    public static function render(
        string $label,
        string $url = '#',
        string $variant = 'secondary',
        string $icon = '',
        bool $disabled = false
    ): void {
        $allowed = ['primary', 'secondary', 'danger', 'link'];
        if (!in_array($variant, $allowed, true)) {
            $variant = 'secondary';
        }

        $classes = ['mcms-button', 'mcms-button--' . $variant];
        if ($disabled) {
            $classes[] = 'is-disabled';
        }

        printf(
            '<a class="%1$s" href="%2$s"%3$s>%4$s<span>%5$s</span></a>',
            esc_attr(self::classes($classes)),
            esc_url($disabled ? '#' : $url),
            $disabled ? ' aria-disabled="true" tabindex="-1"' : '',
            $icon !== '' ? self::icon($icon) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            esc_html($label)
        );
    }
}
