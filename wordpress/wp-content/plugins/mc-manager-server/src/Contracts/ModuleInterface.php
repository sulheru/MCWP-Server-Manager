<?php

declare(strict_types=1);

namespace OptiGrid\MCManagerServer\Contracts;

interface ModuleInterface
{
    /**
     * Identificador estable y con espacio de nombres, por ejemplo: core.summary.
     */
    public function id(): string;

    /**
     * Etiqueta visible del módulo.
     */
    public function label(): string;

    /**
     * Clase Dashicon sin el prefijo "dashicons-".
     */
    public function icon(): string;

    /**
     * Menor valor implica aparición anterior.
     */
    public function priority(): int;

    /**
     * Capacidad WordPress necesaria para acceder al módulo.
     */
    public function capability(): string;

    /**
     * Renderiza el contenido del módulo.
     */
    public function render(): void;
}
