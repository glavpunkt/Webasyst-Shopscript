<?php

/**
 * Класс модуля доставки
 *
 * Отвечает за расчёт цены
 *
 * Class glavpunkthipping
 * @author SokolovMikhail
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
        $cost = 15; // заглушка, убрать когда будут реализованны все доставки
        $deliveres = [];
        $cityTo = $this->getAddress('city');
        $activDeliveres = $this->optionsDelivery;
        $tarifForPunktsInSelectedCity = $this->getPunktsWithTarif($cityTo, $this->cityFrom);

        if (isset($cityTo)) {
            if (isset($activDeliveres[1])) {
                    $i = 0; //  счетчик, нужен для правильного формирования массива с пвз
                foreach ($tarifForPunktsInSelectedCity as $k => $v) {

                    $additional = isset($v["email"]) ? 'Email: ' . $v["email"] . '; ' : '';
                    $additional .= isset($v["phone"]) ? 'Телефон: ' . $v["phone"] . '; ' : '';
                    $additional .= isset($v["work_time"]) ? 'Режим работы: ' . $v["work_time"] . '; ' : '';

                    $deliveres += [
                        'variant_' . $i => [
                            'name' => $v["address"], //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                            'comment' => 'описание необязательно', //необязательное описание варианта доставки
                            'est_delivery' => $v["delivery_period"], //произвольная строка, содержащая  информацию о примерном времени доставки
                            'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                            'rate' => $v['tarif'], //точная стоимость доставки
                            'type' => self::TYPE_PICKUP, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
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
                        'rate' => $cost, //точная стоимость доставки
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
                        'rate' => $cost, //точная стоимость доставки
                        'type' => self::TYPE_TODOOR, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                        'delivery_date' => date("Y-m-d H:i:s"), //дата доставки или интервал дат доставки в формате SQL DATETIME
                        'service' => 'сервис', //название службы доставки для указания компании, выполняющей фактическую доставку
                    ),
                ];
            };
        } else {
            // обязательное сообщение ошибки для пользователя
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

    /**
     * Возвращает массив со всеми пвз в городе доставки без цены
     *
     * @param string $cityTo город куда доставка
     * @return array
     * @throws Exception
     */
    private function getPunkts($cityTo){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://glavpunkt.ru/api/pvz_list?cityFrom=' . $this->cityFrom . '&cityTo=' . $cityTo);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $out = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($out, true);
        if (is_null($res)) {
            throw new Exception("Неверный JSON ответ: " . $out);
        }

        return $res;
    }

    /**
     * Возвращает массив со всеми пвз в городе доставки и ценой
     *
     * @param string $cityTo город куда доставка
     * @param string $cityFrom город отправки из настроек
     * @return array
     * @throws Exception
     */
    private function getPunktsWithTarif($cityTo, $cityFrom){

        $punkts = $this->getPunkts($cityTo);
        $weight = $this->getTotalWeight() == 0 ? 1 : $this->getTotalWeight();
        $price = $this->getTotalPrice();
        $punktsWithTarif = [];
        $i = 0;
        foreach ($punkts as $k => $v){
            $punktsWithTarif += [
                $i => [
                        'serv' => "выдача по РФ",
                        'cityFrom' => $cityFrom,
                        'cityTo' => $cityTo,
                        'weight' => $weight,
                        'price' => $price,
                        'punktId' => $v['id'],
                        'paymentType' => "cash"
                    ],
                ];
            $i++;


        }

        $data = json_encode($punktsWithTarif);

        $curl = curl_init('https://glavpunkt.ru/api-1.1/get_tarifs');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $out = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($out, true);
        if (is_null($res)) {
            throw new Exception("Неверный JSON ответ: " . $out);
        }

        foreach ($res as $kTarif => $vTarif){
            foreach ($punkts as $k => $v){
                if ($kTarif == $k){
                    $punkts[$k] += ['tarif' => $vTarif['tarif']];
                }
            }
        }

        return $punkts;
    }

}
