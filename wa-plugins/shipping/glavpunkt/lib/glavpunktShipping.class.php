<?php

/**
 * Класс модуля доставки
 *
 * Отвечает за расчёт цены
 * @property-read $cod
 *
 * Class glavpunktpointsShipping
 * @author SergeChepikov
 */
class glavpunktShipping extends waShipping
{
    /**
     * Расчёт стоимости доставки и вывод её
     *
     * @return array
     */
    protected function calculate()
    {

        $cost = 15;
        $deliveres = [];
        $region_id = $this->getAddress('city');
        $activDeliveres = $this->optionsDelivery;
        if ($region_id) {
            if (isset($activDeliveres[1])){
                $deliveres += [
                    'variant_1' => array(
                        'name' => 'Пункт Самовывоза', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                        'comment' => 'описание необязательно', //необязательное описание варианта доставки
                        'est_delivery' => 'примерное время доставки', //произвольная строка, содержащая  информацию о примерном времени доставки
                        'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                        'rate' => $cost, //$this->cost, //точная стоимость доставки
                        'type' => self::TYPE_PICKUP, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                        'delivery_date' => date("Y-m-d H:i:s"), //дата доставки или интервал дат доставки в формате SQL DATETIME
                        'service' => 'сервис', //название службы доставки для указания компании, выполняющей фактическую доставку
                        'custom_data'   => [
                            waShipping::TYPE_PICKUP => [
                                'id'          => 'id',//$code,
                                'name'        => 'name',
                                'lat'         => 59.941535, //$point['lat'],
                                'lng'         => 31.257957, //$point['lng'],,
                                'schedule'    => 'shedule',//$info_by_point['schedule'],
                                'description' => 'description', //$point['address'],
                            ]
                        ],
                    ),
                ];
            };
            $deliveres += [
                'variant_12' => array(
                    'name' => 'Пункт Самовывоза', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                    'comment' => 'описание необязательно', //необязательное описание варианта доставки
                    'est_delivery' => 'примерное время доставки', //произвольная строка, содержащая  информацию о примерном времени доставки
                    'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                    'rate' => $cost, //$this->cost, //точная стоимость доставки
                    'type' => self::TYPE_PICKUP, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                    'delivery_date' => date("Y-m-d H:i:s"), //дата доставки или интервал дат доставки в формате SQL DATETIME
                    'service' => 'сервис', //название службы доставки для указания компании, выполняющей фактическую доставку
                    'custom_data'   => [
                        waShipping::TYPE_PICKUP => [
                            'id'          => 'id',//$code,
                            'name'        => 'name',
                            'lat'         => 59.941535, //$point['lat'],
                            'lng'         => 30.257957, //$point['lng'],,
                            'schedule'    => 'shedule',//$info_by_point['schedule'],
                            'description' => 'description', //$point['address'],
                        ]
                    ],
                ),
            ];
            if (isset($activDeliveres[2])){
                $deliveres += [
                    'variant_2' => array(
                        'name' => 'Почта', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                        'comment' => 'описание необязательно', //необязательное описание варианта доставки
                        'est_delivery' => 'примерное время доставки', //произвольная строка, содержащая  информацию о примерном времени доставки
                        'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                        'rate' => $cost, //$this->cost, //точная стоимость доставки
                        'type' => self::TYPE_POST, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                        'delivery_date' => date("Y-m-d H:i:s"), //дата доставки или интервал дат доставки в формате SQL DATETIME
                        'service' => 'сервис', //название службы доставки для указания компании, выполняющей фактическую доставку
                    ),
                ];
            };
            if (isset($activDeliveres[3])){
                $deliveres += [
                    'variant_3' => array(
                        'name' => 'Курьер', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                        'comment' => 'описание необязательно', //необязательное описание варианта доставки
                        'est_delivery' => 'примерное время доставки', //произвольная строка, содержащая  информацию о примерном времени доставки
                        'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                        'rate' => $cost, //$this->cost, //точная стоимость доставки
                        'type' => self::TYPE_TODOOR, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                        'delivery_date' => date("Y-m-d H:i:s"), //дата доставки или интервал дат доставки в формате SQL DATETIME
                        'service' => 'сервис', //название службы доставки для указания компании, выполняющей фактическую доставку
                    ),
                ];
            };
        } else {

            $deliveres = [
                array(
                    'rate'    => null,
                    'comment' => 'Для расчета стоимости доставки укажите регион доставки',
                ),
            ];

        }



        return $deliveres;
    }

    /**
     * Возвращает строку или массив со списком ISO3-кодов валют,
     * для которых плагин может рассчитывать стоимость доставки.
     *
     * Обязательный метод
     *
     * @return array|string
     */
    public function allowedCurrency()
    {
        return $this->currency;
    }

    /**
     * Возвращает строковый идентификатор или массив идентификаторов
     * поддерживаемых плагином единиц измерения веса отправления.
     *
     * Обязательный метод
     *
     * @return array|string
     */
    public function allowedWeightUnit()
    {
        return $this->weight_dimension;
    }

//    public function customFields(waOrder $order)
//    {
//        $this->registerControl('Maplink', array($this, 'openMap'));
//
//        return array(
//            'field_1' => array(
//                'value' => null,
//                'title' => '',
//                'control_type' => 'Maplink',
//                'data' => array(
//                    'affects-rate' => true,
//                ),
//            ),
//        );
//    }

    public function openMap($name, $params = array())
    {
//        echo "<pre>";
//        var_dump( $this->key);
//        echo "</pre>";
        $url_params = array(
            'action_id' => 'rateCOD',
            'plugin_id' => $this->key,
        );
        $url = wa()->getRouteUrl(sprintf('%s/frontend/shippingPlugin', $this->app_id), $url_params, true);
        $totalPrice = wa()->getStorage()->getAll()["shop/cart"]['total'];
        $dataForWidget = "{defaultCity: '" . wa()->getStorage()->getAll()["shop/checkout"]["order"]["region"]["city"] . "'}";
        $html = <<<HTML
        <script async="" src="//glavpunkt.ru/js/punkts-widget/glavpunkt.js" charset="UTF8"></script>
        <a href="javascript:void(0)" class="openMap" onclick="glavpunkt.openMap(selectPunkt, $dataForWidget); return false;">Выбрать пункт выдачи на карте</a>
        <p class="glavpunkt-point"></p>
        <p class="glavpunkt-cost"></p>
        <script type="text/javascript">
        
                function selectPunkt(punktInfo) {
                                console.log(punktInfo);
                                $.getJSON( "//glavpunkt.ru/api/get_tarif", {
                                    'serv': 'выдача',
                                    'cityFrom': '$this->cityFrom',
                                    'cityTo': punktInfo.city,
                                    'punktId': punktInfo.id,
                                    'weight': 1,
                                    'price':'$totalPrice',
                                    'paymentType':'cash'
                                }).done(function(data) {
                                    console.log(data.tarif);
                                    
                                });
    
                                
                                $('.glavpunkt-point').html('Название/метро: ' + punktInfo.name + '<br>' +
                                 'Адрес: ' + punktInfo.address + '<br>' +
                                 'Email: ' + punktInfo.email + '<br>' +
                                 'Телефон: ' + punktInfo.phone + '<br>' + 
                                 'Часы работы: ' + punktInfo.work_time + '<br>'
                                  + punktInfo.deliveryDays);
                            }
        </script>
HTML;
        return $html;
    }

}
