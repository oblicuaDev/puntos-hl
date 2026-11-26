<?php

namespace App\Support;

class PersonName
{
    /**
     * Las bases de H&L traen el nombre en un solo campo y en orden variable
     * ("REYES SANDRA" / "MARIA CLAUDIA RUIZ DURAN"), así que no se puede
     * separar nombre/apellido con certeza. Se parte por la mitad para que
     * nombre + apellido reproduzca exactamente el nombre original.
     *
     * @return array{0: string, 1: string} [nombres, apellidos]
     */
    public static function split(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $words = explode(' ', mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8'));

        if (count($words) === 1) {
            return [$words[0], '-'];
        }

        $half = intdiv(count($words), 2);

        return [implode(' ', array_slice($words, 0, $half)), implode(' ', array_slice($words, $half))];
    }
}
