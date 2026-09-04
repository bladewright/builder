<?php

namespace Bladewright\Tests\Fixtures;

/**
 * A backed enum holds the choices: the values and their names, as a type.
 */
enum Align: string
{
    case Left = 'left';
    case Center = 'center';

    public function label(): string
    {
        return match ($this) {
            self::Left => '左',
            self::Center => '中央',
        };
    }
}
