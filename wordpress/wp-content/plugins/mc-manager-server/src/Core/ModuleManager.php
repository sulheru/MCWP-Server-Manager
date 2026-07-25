<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Core;

use OptiGrid\MCManagerServer\Contracts\ModuleInterface;

final class ModuleManager
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    private bool $discovered = false;

    /**
     * Descubre todos los módulos registrados mediante la API pública.
     *
     * @return array<string, ModuleInterface>
     */
    public function all(): array
    {
        if (!$this->discovered) {
            $this->discover();
        }

        return $this->modules;
    }

    public function get(string $id): ?ModuleInterface
    {
        $modules = $this->all();

        return $modules[$id] ?? null;
    }

    public function firstAccessible(): ?ModuleInterface
    {
        foreach ($this->all() as $module) {
            if (current_user_can($module->capability())) {
                return $module;
            }
        }

        return null;
    }

    public function isAccessible(ModuleInterface $module): bool
    {
        return current_user_can($module->capability());
    }

    /**
     * Valida un identificador con espacio de nombres sin destruir el punto.
     */
    public function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)+$/', $id);
    }

    private function discover(): void
    {
        $this->discovered = true;

        /**
         * Registra módulos del Dashboard Host.
         *
         * Los callbacks deben devolver objetos que implementen ModuleInterface.
         * El núcleo y las extensiones utilizan exactamente el mismo filtro.
         *
         * @param array<int, mixed> $modules
         */
        $candidates = apply_filters('mc_manager_server_modules', []);

        if (!is_array($candidates)) {
            $candidates = [];
        }

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof ModuleInterface) {
                do_action('mc_manager_server_invalid_module', $candidate, 'invalid_contract');
                continue;
            }

            $id = $candidate->id();

            if (!$this->isValidId($id)) {
                do_action('mc_manager_server_invalid_module', $candidate, 'invalid_id');
                continue;
            }

            if (isset($this->modules[$id])) {
                do_action('mc_manager_server_invalid_module', $candidate, 'duplicate_id');
                continue;
            }

            $this->modules[$id] = $candidate;
        }

        uasort(
            $this->modules,
            static function (ModuleInterface $left, ModuleInterface $right): int {
                $priority = $left->priority() <=> $right->priority();

                if ($priority !== 0) {
                    return $priority;
                }

                return strcmp($left->id(), $right->id());
            }
        );
    }
}
