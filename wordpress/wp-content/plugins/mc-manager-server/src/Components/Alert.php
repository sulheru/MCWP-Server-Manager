<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class Alert extends Component
{
    public const TYPE_INFO = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR = 'error';

    public static function render(string $message, string $type = self::TYPE_INFO, string $title = ''): void
    {
        $icons = [
            self::TYPE_INFO => 'info-outline',
            self::TYPE_SUCCESS => 'yes-alt',
            self::TYPE_WARNING => 'warning',
            self::TYPE_ERROR => 'dismiss',
        ];

        if (!isset($icons[$type])) {
            $type = self::TYPE_INFO;
        }
        ?>
        <div class="<?php echo esc_attr(self::classes(['mcms-alert', 'mcms-alert--' . $type])); ?>" role="status">
            <?php echo self::icon($icons[$type]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <div class="mcms-alert__content">
                <?php if ($title !== '') : ?><strong><?php echo esc_html($title); ?></strong><?php endif; ?>
                <p><?php echo esc_html($message); ?></p>
            </div>
        </div>
        <?php
    }
}
