<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class Toolbar extends Component
{
    public static function open(string $label = ''): void
    {
        printf(
            '<div class="mcms-toolbar" role="toolbar"%s>',
            $label !== '' ? ' aria-label="' . esc_attr($label) . '"' : ''
        );
    }

    public static function close(): void
    {
        echo '</div>';
    }
}
