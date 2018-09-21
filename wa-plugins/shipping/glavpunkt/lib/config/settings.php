<?php
return [
    'currency' => [
        'value' => 'RUB',
    ],
    'weight_dimension' => [
        'value' => 'kg'
    ],
    'cityFrom' => [
        'title' => 'Укажите город отправки',
        'control_type' => waHtmlControl::SELECT,
        'options' => [
            [
                'value' => 'Санкт-Петербург',
                'title' => 'Санкт-Петербург',
            ],
            [
                'value' => 'Москва',
                'title' => 'Москва',
            ],
        ],
        'value' => 'Санкт-Петербург',
    ],
];