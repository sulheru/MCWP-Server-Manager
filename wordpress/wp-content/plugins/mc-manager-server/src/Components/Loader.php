<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class Loader extends Component
{
    public static function render(string $label = ''): void
    {
        $label = $label !== '' ? $label : __('Cargando…', 'mc-manager-server');
        ?>
        <div class="mcms-loader" role="status" aria-live="polite">
            <span class="mcms-loader__spinner" aria-hidden="true"></span>
            <span><?php echo esc_html($label); ?></span>
        </div>
        <?php
    }
}
