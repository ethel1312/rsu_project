<?php

namespace App\Services\Programming;

use App\Exceptions\HolidayImportException;
use App\Models\Programming\Holiday;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class HolidayImportService
{
    /** @return array{created: int, skipped: int} */
    public function import(int $year): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(config('holidays.connect_timeout', 5))
                ->timeout(config('holidays.timeout', 15))
                ->withOptions(['allow_redirects' => false])
                ->get(rtrim(config('holidays.api_url'), '/').'/PE/'.$year);
        } catch (ConnectionException $exception) {
            throw new HolidayImportException('No se pudo conectar con el servicio de feriados de Perú. Revise su conexión e intente nuevamente. También puede registrarlos manualmente.', 0, $exception);
        }

        if (! $response->ok()) {
            throw new HolidayImportException('El servicio de feriados no está disponible para el año '.$year.'. No se guardó ningún cambio. Intente nuevamente o registre los feriados manualmente.');
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_is_list($payload) || $payload === [] || count($payload) > 366) {
            throw $this->invalidResponse();
        }

        // Validate the entire response before writing, so malformed rows cannot leave a partial import.
        $records = [];
        foreach ($payload as $item) {
            if (! is_array($item) || Validator::make($item, [
                'date' => ['required', 'date_format:Y-m-d'],
                'name' => ['required', 'string', 'max:1000'],
                'countryCode' => ['required', 'in:PE'],
                'nationalHoliday' => ['required', 'boolean'],
                'holidayTypes' => ['required', 'array', 'min:1'],
                'holidayTypes.*' => ['required', 'string', 'in:Public,Bank,School,Authorities,Optional,Observance'],
            ])->fails() || (int) substr($item['date'], 0, 4) !== $year || trim($item['name']) === '') {
                throw $this->invalidResponse();
            }

            if (! $item['nationalHoliday'] || ! in_array('Public', $item['holidayTypes'], true)) {
                continue;
            }

            // The provider currently marks Easter Sunday as Public in Peru, although
            // the national calendar only includes Maundy Thursday and Good Friday.
            // Primary source: https://busquedas.elperuano.pe/dispositivo/NL/2187453-1
            if (in_array(mb_strtolower(trim($item['name'])), ['easter sunday', 'easter day'], true)) {
                continue;
            }

            $description = $this->description($item);
            $records[$item['date']] = isset($records[$item['date']])
                ? implode(' / ', array_unique([$records[$item['date']], $description]))
                : $description;
            if (mb_strlen($records[$item['date']]) > 1000) {
                throw $this->invalidResponse();
            }
        }

        if ($records === []) {
            throw new HolidayImportException('El servicio no devolvió feriados nacionales de Perú para '.$year.'. No se guardó ningún cambio. Puede registrarlos manualmente.');
        }

        // Ley 31788 (15/06/2023): June 7 is a national holiday from 2024 onward.
        // Nager's live response for 2026 omitted it. Keep this explicit correction
        // separate from movable dates; never replace a manually edited DB record.
        // https://busquedas.elperuano.pe/dispositivo/NL/2187453-1
        if ($year >= 2024) {
            $records[$year.'-06-07'] ??= 'Batalla de Arica y Día de la Bandera';
        }

        return DB::transaction(function () use ($records) {
            $created = 0;
            foreach ($records as $date => $description) {
                $holiday = Holiday::firstOrCreate(['date' => $date], [
                    'description' => $description,
                    'is_active' => true,
                ]);
                $created += (int) $holiday->wasRecentlyCreated;
            }

            return ['created' => $created, 'skipped' => count($records) - $created];
        });
    }

    private function invalidResponse(): HolidayImportException
    {
        return new HolidayImportException('El servicio devolvió datos de feriados incompletos o inválidos. No se guardó ningún cambio. Intente nuevamente o regístrelos manualmente.');
    }

    private function description(array $item): string
    {
        if (isset($item['localName']) && is_string($item['localName']) && trim($item['localName']) !== '' && mb_strlen($item['localName']) <= 1000) {
            return trim($item['localName']);
        }

        // v4 supplies English names. Translate familiar names without inventing dates or holidays.
        $translations = [
            "new year's day" => 'Año Nuevo',
            'maundy thursday' => 'Jueves Santo',
            'good friday' => 'Viernes Santo',
            'labour day' => 'Día del Trabajo',
            'labor day' => 'Día del Trabajo',
            'battle of arica and flag day' => 'Batalla de Arica y Día de la Bandera',
            'battle of arica and day of the flag' => 'Batalla de Arica y Día de la Bandera',
            'saint peter and saint paul' => 'San Pedro y San Pablo',
            'saints peter and paul' => 'San Pedro y San Pablo',
            'peruvian air force day' => 'Día de la Fuerza Aérea del Perú',
            'air force day' => 'Día de la Fuerza Aérea del Perú',
            'independence day' => 'Día de la Independencia',
            'independence day holiday' => 'Fiestas Patrias',
            'battle of junín' => 'Batalla de Junín',
            'battle of junin' => 'Batalla de Junín',
            'saint rose of lima' => 'Santa Rosa de Lima',
            'santa rosa de lima' => 'Santa Rosa de Lima',
            'battle of angamos' => 'Combate de Angamos',
            "all saints' day" => 'Día de Todos los Santos',
            'all saints day' => 'Día de Todos los Santos',
            'immaculate conception' => 'Inmaculada Concepción',
            'immaculate conception day' => 'Inmaculada Concepción',
            'battle of ayacucho' => 'Batalla de Ayacucho',
            'christmas day' => 'Navidad',
            'christmas' => 'Navidad',
        ];

        $name = trim($item['name']);

        return $translations[mb_strtolower($name)] ?? $name;
    }
}
