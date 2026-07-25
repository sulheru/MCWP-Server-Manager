<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

abstract class Component
{
    /**
     * @param array<int, string> $classes
     */
    protected static function classes(array $classes): string
    {
        $classes = array_values(array_filter(array_map('sanitize_html_class', $classes)));

        return implode(' ', $classes);
    }

    protected static function icon(string $icon): string
    {
        $icon = sanitize_html_class($icon);

        if ($icon === '') {
            return '';
        }

        return sprintf(
            '<span class="dashicons dashicons-%s" aria-hidden="true"></span>',
            esc_attr($icon)
        );
    }
}
