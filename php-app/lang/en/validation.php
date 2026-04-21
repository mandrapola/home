<?php

return [
    'required' => 'The :attribute field is required.',
    'integer' => 'The :attribute field must be an integer.',
    'numeric' => 'The :attribute field must be a number.',
    'boolean' => 'The :attribute field must be true or false.',
    'string' => 'The :attribute field must be a string.',
    'uuid' => 'The :attribute field must be a valid UUID.',
    'max' => [
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'in' => 'The selected :attribute is invalid.',

    'attributes' => [
        'send_interval_seconds' => 'Send Interval, sec',
        'chart_range_hours' => 'Chart Range, h',
        'desired_digital_value' => 'Pin State',
        'label' => 'Name',
        'unit' => 'Unit',
        'is_monitored' => 'Monitoring',
        'show_on_chart' => 'Show on Chart',
        'controller_id' => 'Controller ID',
        'pin_id' => 'Pin ID',
        'name' => 'Name',
        'discription' => 'Description',
    ],
];
