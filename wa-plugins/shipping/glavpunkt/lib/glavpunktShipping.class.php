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
        $city = $this->getAddress('city');
        //$region = $this->getAddress('region');
        $activDeliveres = $this->optionsDelivery;
        $punktsInSelectedCity = $this->getPunkts($city);

        var_dump($this->getTarif());

        if (isset($city)) {
            if (isset($activDeliveres[1])) {
                    $i = 0;
                foreach ($punktsInSelectedCity as $k => $v) {

                    $additional = isset($v["email"]) ? 'Email: ' . $v["email"] . '; ' : '';
                    $additional .= isset($v["phone"]) ? 'Телефон: ' . $v["phone"] . '; ' : '';
                    $additional .= isset($v["work_time"]) ? 'Режим работы: ' . $v["work_time"] . '; ' : '';

                    $deliveres += [
                        'variant_' . $i => [
                            'name' => $v["address"], //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                            'comment' => 'описание необязательно', //необязательное описание варианта доставки
                            'est_delivery' => $v["delivery_period"], //произвольная строка, содержащая  информацию о примерном времени доставки
                            'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                            'rate' => $cost, //$this->cost, //точная стоимость доставки
                            'type' => self::TYPE_PICKUP, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                            //'delivery_date' => $v["delivery_period"], //дата доставки или интервал дат доставки в формате SQL DATETIME
                            'service' => $v["operator"] != "gp" ? $v['operator'] : 'Glavpunkt', //название службы доставки для указания компании, выполняющей фактическую доставку
                            'custom_data' => [
                                waShipping::TYPE_PICKUP => [
                                    'id' => $v['id'],
                                    'lat' => $v["geo_lat"],
                                    'lng' => $v["geo_lng"],
                                    'additional' => $additional,
                                    'payment' => [
                                        waShipping::PAYMENT_TYPE_CARD    => isset($v["card_accepted"]) ? true : false,
                                        waShipping::PAYMENT_TYPE_CASH    => true,
                                    ],
                                ],
                            ],
                        ],
                    ];
                    $i++;
                }
            };

            if (isset($activDeliveres[2])){
                $deliveres += [
                    'variant_post' => array(
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
                    'variant_todoor' => array(
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

    private function getPunkts($city){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://glavpunkt.ru/api/pvz_list?cityFrom=' . $this->cityFrom . '&cityTo=' . $city);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $out = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($out, true);
        if (is_null($res)) {
            throw new Exception("Неверный JSON ответ: " . $out);
        }

        return $res;
    }

    private function getTarif(){


        $data1 = [
             'serv' => "выдача",
             'cityFrom' => "Санкт-Петербург",
             'cityTo' => "Москва",
             'weight' => 1,
             'price' => 5000,
             'paymentType' => "cash"
        ];

        $data = json_encode($data1);

        $curl = curl_init('https://glavpunkt.ru/api-1.1/get_tarifs');
        //curl_setopt($curl, CURLOPT_URL, 'https://glavpunkt.ru/api-1.1/get_tarifs');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $out = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($out, true);
        if (is_null($res)) {
            throw new Exception("Неверный JSON ответ: " . $out);
        }

        return $res;
    }

}
