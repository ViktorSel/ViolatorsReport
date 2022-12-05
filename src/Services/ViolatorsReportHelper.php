<?php

namespace Modules\Ui\Services\ViolatorsReport;

use Illuminate\Contracts\Auth\Authenticatable;

class ViolatorsReportHelper
{
    public function get_plazas_code(array $data): array
    {
        $plazas_code = [];
        foreach ($data as $key => $value) {
            $plazas_code[] = $key;
        }
        return $plazas_code;
    }

    public function get_plazas_names(array $data):array
    {
        $plazas_name = [];
        foreach ($data as $key => $value) {
            $plazas_name[] = $value;
        }
        return $plazas_name;
    }

    /**
     * @return Authenticatable
     */
    public function getEmployer(): Authenticatable
    {
        return auth()->user();
    }
}
