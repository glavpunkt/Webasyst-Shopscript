<?php
return array(
    'currency' => array(
        'value' => 'RUB',
    ),
    'weight_dimension' => array(
        'value' => 'kg'
    ),
    'cityFrom' => array(
        'title' => 'Укажите город отправки',
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => 'Санкт-Петербург',
                'title' => 'Санкт-Петербург',),
            array('value' => 'Москва',
                'title' => 'Москва',),
        ),
        'value' => 'Санкт-Петербург',
    ),
    'optionsDelivery' => array(
        'title' => 'Выберите необходимые способы доставки',
        'control_type' => waHtmlControl::GROUPBOX,
        'options' => array(
            1 => 'Пункты самовывоза',
            2 => 'Доставка Почтой РФ',
            3 => 'Курьерская доставка',
        ),
    )
);