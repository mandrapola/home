<?php

return [
    'required' => 'Поле :attribute обязательно.',
    'integer' => 'Поле :attribute должно быть целым числом.',
    'numeric' => 'Поле :attribute должно быть числом.',
    'boolean' => 'Поле :attribute должно быть true или false.',
    'string' => 'Поле :attribute должно быть строкой.',
    'uuid' => 'Поле :attribute должно быть корректным UUID.',
    'max' => [
        'numeric' => 'Поле :attribute не должно быть больше :max.',
        'string' => 'Поле :attribute не должно быть длиннее :max символов.',
    ],
    'min' => [
        'numeric' => 'Поле :attribute должно быть не меньше :min.',
        'string' => 'Поле :attribute должно быть не короче :min символов.',
    ],
    'in' => 'Выбранное значение для :attribute недопустимо.',

    'attributes' => [
        'send_interval_seconds' => 'Интервал отправки, сек',
        'chart_range_hours' => 'Диапазон графика, ч',
        'desired_digital_value' => 'Состояние пина',
        'label' => 'Название',
        'unit' => 'Единица',
        'is_monitored' => 'Мониторинг',
        'show_on_chart' => 'Показывать на графике',
        'invert_digital_logic' => 'Инверсия логики',
        'controller_id' => 'ID контроллера',
        'pin_id' => 'ID пина',
        'name' => 'Имя',
        'discription' => 'Описание',
    ],
];
