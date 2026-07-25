<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class Progress extends Component
{
    public static function render(string $label, float $value, string $caption = ''): void
    {
        $value = max(0.0, min(100.0, $value));
        ?>
        <div class="mcms-progress">
            <div class="mcms-progress__header">
                <span><?php echo esc_html($label); ?></span>
                <strong><?php echo esc_html(number_format_i18n($value, 0)); ?>%</strong>
            </div>
            <div class="mcms-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr((string) $value); ?>">
                <span style="width: <?php echo esc_attr((string) $value); ?>%"></span>
            </div>
            <?php if ($caption !== '') : ?><p><?php echo esc_html($caption); ?></p><?php endif; ?>
        </div>
        <?php
    }
}
