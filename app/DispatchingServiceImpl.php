<?php

namespace App;

use App\Services\DispatchingService;

class DispatchingServiceImpl implements DispatchingService
{

    public function dispatch(array $candidatesIds, array $evaluatedIds): array
    {
        $result = [];
        $n = count($evaluatedIds);

        // Déterminer la taille du groupe (3 ou 2 selon la taille de e)
        $group_size = ($n >= 3) ? 3 : max(1, $n);

        foreach ($candidatesIds as $i => $ci) {
            $start = $i % $n;
            $group = [];

            for ($j = 0; $j < $group_size; $j++) {
                $index = ($start + $j) % $n;
                $group[] = $evaluatedIds[$index];
            }

            $result["c$ci"] = $group;
        }

        return $result;
    }
}


$dispatchingService = new DispatchingServiceImpl();
$res = $dispatchingService->dispatch([1, 2, 3, 4, 5, 6], [1, 2]);

print_r($res);
