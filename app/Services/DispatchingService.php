<?php

namespace App\Services;

interface DispatchingService
{
    public function dispatch(array $candidatesIds, array $evaluatedIds): array;
}
