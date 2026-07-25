<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class Card extends Component
{
    public static function render(
        string $title,
        string $value,
        string $description = '',
        string $icon = '',
        string $status = ''
    ): void {
        ?>
        <article class="mcms-card">
            <?php if ($icon !== '') : ?>
                <div class="mcms-card__icon"><?php echo self::icon($icon); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
            <div class="mcms-card__body">
                <div class="mcms-card__header">
                    <h3><?php echo esc_html($title); ?></h3>
                    <?php if ($status !== '') : ?>
                        <span class="mcms-card__status"><?php echo esc_html($status); ?></span>
                    <?php endif; ?>
                </div>
                <p class="mcms-card__value"><?php echo esc_html($value); ?></p>
                <?php if ($description !== '') : ?>
                    <p class="mcms-card__description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }
}
