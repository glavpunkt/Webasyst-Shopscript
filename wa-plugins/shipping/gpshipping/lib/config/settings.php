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
            'pickup' => 'Пункты самовывоза',
            'post' => 'Доставка Почтой РФ',
            'courier' => 'Курьерская доставка',
        ),
    ),
    'prefixId' => array(
        'title' => 'Префикс для номера заказа в ЛК',
        'control_type' => waHtmlControl::INPUT,
        'value'        => '',
    ),
    'pickupAddress' => array(
        'title' => 'Адрес для забора',
        'control_type' => waHtmlControl::INPUT,
        'value'        => '',
    ),
    'ApiLogin' => array(
        'title' => 'Логин ЛК',
        'control_type' => waHtmlControl::INPUT,
        'value'        => '',
    ),
    'ApiToken' => array(
        'title' => 'Токен ЛК',
        'control_type' => waHtmlControl::INPUT,
        'value'        => '',
    ),
    'MethodDelivery' => array(
        'title' => 'Выебите способ передачи товара в Главпункт',
        'control_type' => waHtmlControl::SELECT,
        'options' => array(
            array('value' => 'self_delivery',
                'title' => 'Самопривоз',),
            array('value' => 'pickup',
                'title' => 'Забор',),
        ),
    ),
    'weightDefault' => array(
        'title' => 'Вес по умолчанию (кг)',
        'control_type' => waHtmlControl::INPUT,
        'value'        => 1,
    ),
    'daysForCourier' => array(
        'title' => 'Кол-во дней добавляемых к сроку доставки для курьерской доставки',
        'control_type' => waHtmlControl::INPUT,
        'value'        => 0,
    ),
    'costOfTransfer' => array(
        'title' => 'Учитывать стоимость перемещения между пунктами',
        'control_type' => waHtmlControl::CHECKBOX,
        'options' => array(
            'on' => 'Да',
        ),
    ),
    'freeShippingMSK' => array(
        'title' => 'Бесплатная доставка/выдача для Москвы при цене от',
        'control_type' => waHtmlControl::INPUT,
        'value'        => '',
    ),
    'freeShippingSPB' => array(
        'title' => 'Бесплатная доставка/выдача для СПБ при цене от',
        'control_type' => waHtmlControl::INPUT,
        'value'        => '',
    ),
    'fixedShippingMSK' => array(
        'title' => 'Фиксированная стоимость доставки для Москвы',
        'control_type' => waHtmlControl::INPUT,
        'value'        => '',
    ),
    'fixedShippingSPB' => array(
        'title' => 'Фиксированная стоимость доставки для СПБ',
        'control_type' => waHtmlControl::INPUT,
        'value'        => '',
    ),
);
