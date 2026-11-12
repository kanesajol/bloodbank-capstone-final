<?php

namespace App\Models;

use App\Support\BloodBank;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'location',
    'patient_name',
    'age',
    'birthdate',
    'sex',
    'blood_component',
    'municipality',
    'province',
    'barangay',
    'blood_type',
    'number_of_bags',
    'month',
    'year',
    'reporting_period',
    'date_submitted',
])]
class ConsumptionRecord extends Model
{
    public function getFullAddressAttribute(): string
    {
        return BloodBank::cleanAddressString((string) ($this->location ?? ''));
    }

    protected function casts(): array
    {
        return [
            'number_of_bags' => 'integer',
            'month' => 'integer',
            'year' => 'integer',
            'birthdate' => 'date',
            'date_submitted' => 'date',
        ];
    }
}
