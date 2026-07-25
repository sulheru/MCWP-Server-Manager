<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class EmptyState extends Component
{
    public static function render(string $title, string $description, string $icon = 'info-outline'): void
    {
        ?>
        <div class="mcms-empty-state">
            <?php echo self::icon($icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <h3><?php echo esc_html($title); ?></h3>
            <p><?php echo esc_html($description); ?></p>
        </div>
        <?php
    }
}
