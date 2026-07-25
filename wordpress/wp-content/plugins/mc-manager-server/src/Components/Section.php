<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Components;

final class Section extends Component
{
    public static function open(string $title, string $description = '', string $id = ''): void
    {
        $id = $id !== '' ? sanitize_html_class($id) : 'mcms-section-' . wp_unique_id();
        ?>
        <section class="mcms-section" aria-labelledby="<?php echo esc_attr($id); ?>">
            <header class="mcms-section__header">
                <div>
                    <h3 id="<?php echo esc_attr($id); ?>"><?php echo esc_html($title); ?></h3>
                    <?php if ($description !== '') : ?><p><?php echo esc_html($description); ?></p><?php endif; ?>
                </div>
            </header>
            <div class="mcms-section__body">
        <?php
    }

    public static function close(): void
    {
        echo '</div></section>';
    }
}
