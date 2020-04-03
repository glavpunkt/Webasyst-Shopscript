<?php

/**
 * Класс модуля доставки
 *
 * Отвечает за расчёт цены
 *
 * Class gpshippingShipping
 * @author SokolovMikhail
 */
class gpshippingShipping extends waShipping
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
                'serv' => 'выдача по РФ',
                'cityFrom' => $cityFrom,
                'cityTo' => $cityTo,
                'weight' => $weight,
                'price' => $price,
                'punktId' => $v['id'],
                'paymentType' => 'cash'
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

            $additional = (isset($v['email']) && $v['email'] != '' ? 'Email: ' . $v['email'] . '; ' : '');
            $additional .= (isset($v['phone']) && $v['phone'] != '' ? 'Телефон: ' . $v['phone'] . '; ' : '');

            $deliveries[$v['id']] = array(
                    'name' => 'Пункт выдачи ' . (isset($v['metro']) ? $v['metro'] : $v['address']), //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                    'est_delivery' => $this->periodDelivery($v['delivery_period'], '0'), //произвольная строка, содержащая  информацию о примерном времени доставки
                    'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                    'rate' => $v['tarif'], //точная стоимость доставки
                    'type' => waShipping::TYPE_PICKUP, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                    'service' => 'Главпункт', //название службы доставки для указания компании, выполняющей фактическую доставку
                    'custom_data' => array(
                        'pickup' => array(
                            'id' => $v['id'],
                            'lat' => $v['geo_lat'],
                            'lng' => $v['geo_lng'],
                            'additional' => $additional,
                            'way' => $v['address'],
                            'payment' => array(
                                waShipping::PAYMENT_TYPE_CARD => (isset($v['card_accepted']) && $v['card_accepted'] == '1'),
                                waShipping::PAYMENT_TYPE_CASH => true,
                            ),
                            'schedule' => $v['work_time'],
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
            $estDelivery = $this->periodDelivery($tarif['period'], '0');
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

        $estDelivery = $this->periodDelivery($tarif['period'], $this->daysForCourier);

        $tarif['tarif'] = $this->checkCostShipping($tarif['tarif']);

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
     * Set package state into waShipping::STATE_DRAFT
     * Вызывается при оформлении заказа и при смене статуса заказа на оплачен
     * @param waOrder $order
     * @param array   $shipping_data
     * @return null|string|string[] null, error or shipping data array
     */
    protected function draftPackage(waOrder $order, $shipping_data = array())
    {
//        foreach ($order as $k => $v) {
//            var_dump($k);
//        }
        //var_dump($order);
        //file_put_contents('index.txt', 'draftPackage' . date('H:i:s') . '\n', FILE_APPEND);
        return null;
    }

    /**
     * Set package state into waShipping::STATE_READY
     * Вызывается при смене статуса заказа на отправлен
     * @param waOrder $order
     * @param array   $shipping_data
     * @return null|string|string[] null, error or shipping data array
     */
    protected function readyPackage(waOrder $order, $shipping_data = array())
    {
        if ($this->ApiLogin == '' || $this->ApiToken == '') {
            //@TODO сделать выброс ошибки
            return null;
        }

        $data['serv'] = 'выдача';

        if ($order->params['shipping_rate_id'] == 'courier') {
            $data['serv'] = 'курьерская доставка';
        }

        if ($order->params['shipping_rate_id'] == 'post') {
            $data['serv'] = 'почта';
        }

        $data['pvz_id'] = $order->shipping_rate_id;
        $data['login'] = $this->ApiLogin;
        $data['token'] = $this->ApiToken;
        $data['method'] = $this->MethodDelivery;
        $data['punkt_id'] = 'Moskovskaya-A16';
        $data['pickup_id'] = $this->prefixId . $order->id;
        $data['pickup_city'] = $this->cityFrom;
        $data['pickup_date'] = date('H:i');
        $data['pickup_interval'] = '10-18';
        $data['pickup_address'] = $this->pickupAddress;
        $data['pvz_id'] = $order->shipping_rate_id;
        $data['sku'] = $this->prefixId . $order->id;
        $data['price'] = $order->total;
        $data['insurance_val'] = $order->subtotal;
        $data['buyer_phone'] = ifempty($order->shipping_address['phone'], $order->getContactField('phone'));
        $data['buyer_fio'] = ifempty($order->shipping_address['firstname'], $order->getContactField('firstname'));
        $data['buyer_email'] = ifempty($order->shipping_address['email'], $order->getContactField('email'));

        foreach ($order->items as $k => $v) {
            $data['parts'][$k] = array(
                'name' => $v['name'],
                'price' => $v['price'], // Сумма к получению за единицу товара
                'insurance_val' => $v['price'], // Оценочная (страховая) стоимость единицы товара
                'num' => $v['quantity'], // Количество позиций товара (по-умолчанию 1)
                'weight' => ($v['weight'] == 0 ? 1 : $v['weight'])
            );
            $data['weight'] +=  (int)($v['weight'] == 0 ? 1 : $v['weight']);
        }

        //file_put_contents('index.txt', $order->params['shipping_rate_id'], FILE_APPEND);
        $result = $this->addToLkGlavpunkt($data);

        return null;//$result;
    }

    /**
     * Set package state into waShipping::STATE_CANCELED
     * Вызывается при возврате заказа
     * @param waOrder $order
     * @param array   $shipping_data
     * @return null|string|string[] null, error or shipping data array
     */
    protected function cancelPackage(waOrder $order, $shipping_data = array())
    {
        //file_put_contents('index.txt', $order->shipping_data['order_id'], FILE_APPEND);
        //file_put_contents('index.txt', serialize($order), FILE_APPEND);
        //var_dump('cancelPackage');
        return null;
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
            throw new Exception('Неверный JSON ответ: ' . $out);
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
     * @return string
     */
    private function periodDelivery($period, $extraDays)
    {
        preg_match_all('/\d+/', $period, $match);
        if (count($match[0]) == 1 && $extraDays == 0) {
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
            return '';
        }

        return $description;
    }

    /**
     * Создание описания при условии одного числа в периоде
     *
     * @return string
     */
    private function printDescriptionForOneDay($num)
    {
        return "$num "  . ($num >= 5
                ? 'дней'
                : ($num > 1 ? 'дня' : 'день')
            );
    }

    /**
     * Проверяет параметы доставки и возвращает либо false либо стоимоть доставки
     *
     * @param string $cost
     * @return string
     */
    private function checkCostShipping($cost)
    {
        if ($this->getAddress('city') == 'Санкт-Петербург') {
            if ($this->fixedShippingSPB !== '') {
                $cost = $this->fixedShippingSPB;
            }

            if ($this->freeShippingSPB !== '' && (int)$this->freeShippingSPB < (int)$this->getTotalPrice()) {
                $cost = '0';
            }
        }

        if ($this->getAddress('city') == 'Москва') {
            if ($this->fixedShippingMSK !== '') {
                $cost = $this->fixedShippingMSK;
            }

            if ($this->freeShippingMSK !== '' && (int)$this->freeShippingMSK < (int)$this->getTotalPrice()) {
                $cost = '0';
            }
        }

        return $cost;
    }

    /**
     * Собирает параметры для выгрузи в ЛК Главпункт и отправляет
     *
     * @param array $data
     * @return array
     */
    private function addToLkGlavpunkt($data)
    {
        $data1 = array(
            'login' => $data['login'], // логин интернет-магазина
            'token' => $data['token'], // token для авторизации
            'shipment_options' => array(
                'skip_existed ' => 1, // Если какой-либо из заказов уже создан, то пропустить его.
                // В противном случае ни один из заказов в запросе не будет создан.
                'method' => $data['method'], // Метод отгрузки self_delivery - самопривоз, или pickup - забор.
                'punkt_id' => 'Moskovskaya-A16', // Пункт отгрузки, если метод отгрузки self_delivery
                'pickup_id' => $data['pickup_id'], // Номер заявки на забор, если метод отгрузки pickup

                // Следующие параметры передавайте, только если нужно создать новый забор (т.е. нужен забор, но у вас еще нет pickup_id)
                'pickup_city' => $data['pickup_city'], // Кладр города (или 'SPB' или 'Санкт-Петербург').
                'pickup_date' => $data['pickup_date'], // Дата забора в формате 'Y-m-d'. Должна быть не раньше завтрашнего дня
                'pickup_interval' => $data['pickup_interval'], // Интервал забора
                'pickup_address' => $data['pickup_address']
                ),
            'orders' => array(
                // Заказ на выдачу в ПВЗ
                array(
                    'serv' => $data['serv'],
                    'pvz_id' => $data['pvz_id'],
                    'sku' => $data['sku'],
                    'price' => $data['price'], // Сумма к получению. Если передан 0, значит заказ предоплачен.
                    'buyer_phone' => $data['buyer_phone'],
                    'buyer_fio' => $data['buyer_fio'],
                    'buyer_email' => $data['buyer_email'],
                    'insurance_val' => $data['insurance_val'], // Оценочная (страховая) стоимость заказа
                    'weight' => $data['weight'], // Общий вес заказа в кг.
                    'parts' => $data['parts']
                    )
                ),
            );

        $url = 'https://glavpunkt.ru/api/create_shipment';

        $result = $this->request($url, $data1);
        file_put_contents('index.txt', $result, FILE_APPEND);

        return $result;
    }
}