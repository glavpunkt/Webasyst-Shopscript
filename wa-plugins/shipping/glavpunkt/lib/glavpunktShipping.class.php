<?php

/**
 * Класс модуля доставки
 *
 * Отвечает за расчёт цены
 *
 * Class glavpunktShipping
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
        $deliveries = array();
        $cityTo = $this->getAddress('city');

        if (isset($cityTo)) {
            if (isset($this->optionsDelivery['pickup'])) {
                $deliveries = $this->getArrayPickup($cityTo);
            }

            if (isset($this->optionsDelivery['post'])) {
                $deliveries['post'] = $this->getArrayPost($cityTo);
            }

            if (isset($this->optionsDelivery['courier'])) {
                $deliveries['courier'] = $this->getArrayTodoor($cityTo);
            }
        } else {
            // обязательное сообщение ошибки для пользователя
            $deliveries = array(
                array(
                    'rate'    => null,
                    'comment' => 'Для расчета стоимости доставки укажите регион доставки',
                ),
            );
        }

        return $deliveries;
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
     * Возвращает массив со всеми пвз в городе доставки и ценой
     *
     * @param string $cityTo название города назначения
     * @param string $cityFrom название город отправки из настроек
     * @return array
     * @throws Exception
     */
    private function punkts($cityTo, $cityFrom)
    {
        $params = array(
            'cityFrom' => $this->cityFrom,
            'cityTo' => $this->getAddress('city')
        );

        $punkts = $this->request('https://glavpunkt.ru/api/pvz_list?' . http_build_query($params));

        $weight = $this->getTotalWeight() == 0 ? $this->weightDefault : $this->getTotalWeight();
        $price = $this->getTotalPrice();
        $data = array();

        foreach ($punkts as $k => $v){
            $data[$v['id']] = array(
                'serv' => "выдача по РФ",
                'cityFrom' => $cityFrom,
                'cityTo' => $cityTo,
                'weight' => $weight,
                'price' => $price,
                'punktId' => $v['id'],
                'paymentType' => "cash"
            );
        }

        $params = array(
            'cityFrom' => $this->cityFrom,
            'cityTo' => $this->getAddress('city'),
            'serv' => 'выдача',
            'paymentType' => 'cash',
            'weight' => $weight,
            'price' => $price
        );

        if (isset($this->costOfTransfer['on'])) {
            $params['transfer'] = 'on';
        }

        $url = 'https://glavpunkt.ru/api/get_tarif?' . http_build_query($params);
        $tarif = $this->request($url);

        if (isset($tarif['tarifRange'])) {
            $url = 'https://glavpunkt.ru/api-1.1/get_tarifs';
            $tarif = $this->request($url, $data);
            
            foreach ($tarif as $kTarif => $vTarif) {
                foreach ($punkts as $k => $v) {
                    if ($kTarif == $k) {
                        $punkts[$k]['tarif'] = $vTarif['tarif'];
                    }
                }
            }
        } else {
            foreach ($punkts as $k => $v) {
                $punkts[$k]['tarif'] = $tarif['tarif'];
            }
        }

        return $punkts;
    }

    private function getArrayPickup($cityTo)
    {
        $tarifForPunktsInSelectedCity = $this->punkts($cityTo, $this->cityFrom);

        foreach ($tarifForPunktsInSelectedCity as $k => $v) {

            $additional = isset($v["email"]) ? 'Email: ' . $v["email"] . '; ' : '';
            $additional .= isset($v["phone"]) ? 'Телефон: ' . $v["phone"] . '; ' : '';
            $additional .= isset($v["work_time"]) ? 'Режим работы: ' . $v["work_time"] . '; ' : '';

            $deliveries[$v['id']] = array(
                    'name' => $v["address"], //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                    'est_delivery' => $v["delivery_period"], //произвольная строка, содержащая  информацию о примерном времени доставки
                    'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                    'rate' => $v['tarif'], //точная стоимость доставки
                    'type' => waShipping::TYPE_PICKUP, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                    'service' => 'Главпункт', //название службы доставки для указания компании, выполняющей фактическую доставку
                    'custom_data' => array(
                        'pickup' => array(
                            'id' => $v['id'],
                            'lat' => $v["geo_lat"],
                            'lng' => $v["geo_lng"],
                            'additional' => $additional,
                            'payment' => array(
                                waShipping::PAYMENT_TYPE_CARD => (isset($v["card_accepted"]) && $v["card_accepted"] == "1"),
                                waShipping::PAYMENT_TYPE_CASH => true,
                            ),
                        ),
                    ),
                );
            };

        return $deliveries;
    }

    private function getArrayPost($cityTo)
    {
        $weight = $this->getTotalWeight() == 0 ? $this->weightDefault : $this->getTotalWeight();
        $zip = $this->getAddress('zip');
        $cost = '';
        $estDelivery = '';

        if ($this->cityFrom == 'Москва') {
            $cityFrom = 'MSK';
        } else {
            $cityFrom = 'SPB';
        }

        if (isset($zip)) {
            $params = array(
                'cityFrom' => $cityFrom,
                'index' => $zip,
                'paymentType' => 'cash',
                'weight' => $weight,
                'price' => $this->getTotalPrice()
            );

            $url = 'https://glavpunkt.ru/api/get_pochta_tarif?' . http_build_query($params);
            $tarif = $this->request($url);

            if ($tarif['result'] == 'error') {
                return null;
            }

            $cost = $tarif['tarifTotal'];
            $estDelivery = $tarif['period'];
        }

        return $post = array(
                'name' => 'Почта РФ', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                'est_delivery' => $estDelivery, //произвольная строка, содержащая  информацию о примерном времени доставки
                'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                'rate' => $cost, //точная стоимость доставки
                'type' => waShipping::TYPE_POST, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                'service' => 'Главпункт', //название службы доставки для указания компании, выполняющей фактическую доставку
        );
    }

    private function getArrayTodoor($cityTo)
    {
        $estDelivery = '';
        $weight = $this->getTotalWeight() == 0 ? $this->weightDefault : $this->getTotalWeight();

        $params = array(
            'cityFrom' => $this->cityFrom,
            'cityTo' => $this->getAddress('city'),
            'serv' => 'курьерская доставка',
            'paymentType' => 'cash',
            'weight' => $weight,
            'price' => $this->getTotalPrice()
        );

        $url = 'https://glavpunkt.ru/api/get_tarif?' . http_build_query($params);
        $tarif = $this->request($url);

        if ($tarif['result'] == 'error') {
            return null;
        }

        $estDelivery = $tarif['period'];

        if ( $this->daysForCourier != '') {
            $estDelivery = ($this->periodDelivery($tarif['period'], $this->daysForCourier))['description'];
        }

        return $todoor = array(
                'name' => 'Курьерская доставка Главпункт', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                'est_delivery' => $estDelivery, //произвольная строка, содержащая  информацию о примерном времени доставки
                'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                'rate' => $tarif['tarif'], //точная стоимость доставки
                'type' => waShipping::TYPE_TODOOR, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                'service' => 'Главпункт', //название службы доставки для указания компании, выполняющей фактическую доставку
        );
    }

    /**
     * Выполнение запроса
     * 
     * @param string $url 
     * @param array $data массив параметров для передачи POST запросом
     * @return array
     * @throws Exception
     */
    private function request($url, $data = null)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);

        if (!is_null($data)) {
            $encodeData = json_encode($data);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encodeData);
            curl_setopt($curl, CURLOPT_POST, true);
        }

        $out = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($out, true);

        if (is_null($res)) {
            throw new Exception("Неверный JSON ответ: " . $out);
        }

        return $res;
    }

    /**
     * Обязательные поля для каждого типа доставки
     *
     * @param array $service
     * @return array
     */
    public function requestedAddressFieldsForService($service)
    {
        if ($service['type'] == 'todoor') {

            return array(
                'street' => array('required' => true),
                'city' => array('required' => true)
            );
        } elseif ($service['type'] == 'post') {

            return array(
                'zip' => array('cost' => true, 'required' => true)
            );
        }
    }

    /**
     * Вывод периода доставки
     *
     * @param string $period
     * @param string $extraDays
     * @return array
     * [
     *  "min_days" => 2,
     *  "max_days" => 5,
     *  "description" => 'от 2 до 5 дней'
     * ]
     */
    private function periodDelivery($period, $extraDays)
    {
        preg_match_all('/\d+/', $period, $match);
        if (count($match[0]) == 1 && $extraDays === 0) {
            $min_days = $match[0][0];
            $max_days = $match[0][0];
            $description = $this->printDescriptionForOneDay($match[0][0]);
        } elseif (count($match[0]) == 1 && $extraDays > 0) {
            $min_days = $match[0][0];
            $max_days = $match[0][0] + $extraDays;
            $description = "от $min_days до $max_days дней";
        } elseif (count($match[0]) == 2) {
            $min_days = $match[0][0];
            $max_days = $match[0][1] + $extraDays;
            $description = "от $min_days до $max_days дней";
        } else {
            return [];
        }

        return [
            "min_days" => $min_days,
            "max_days" => $max_days,
            "description" => $description
        ];
    }

    /**
     * Создание описания при условии одного числа в периоде
     *
     * @return string
     */
    private function printDescriptionForOneDay($num)
    {
        return "$num " . ($num >= 5
                ? "дней"
                : ($num > 1 ? "дня" : "день")
            );
    }
}
