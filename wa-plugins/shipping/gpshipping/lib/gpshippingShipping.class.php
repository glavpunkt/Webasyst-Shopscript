<?php

/**
 * Класс модуля доставки
 *
 * Отвечает за расчёт цены
 * Метод draftPackage вызывается при оформлении заказа и при смене статуса заказа на оплачен
 * Метод cancelPackage вызывается при возврате заказа
 *
 * Class gpshippingShipping
 * @author SokolovMikhail
 */
class gpshippingShipping extends waShipping
{
    /**
     * Расчёт стоимости доставки и вывод её
     * Обязательный метод
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
                $deliveries['post'] = $this->getArrayPost();
            }

            if (isset($this->optionsDelivery['courier'])) {
                $deliveries['courier'] = $this->getArrayTodoor();
            }
        } else {
            // обязательное сообщение ошибки для пользователя
            $deliveries = array(
                array(
                    'rate' => null,
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
     * Метод возвращает массив для типа доставки pickup
     *
     * @param string $cityTo город назначения
     * @return array
     * @throws waException
     */
    private function getArrayPickup($cityTo)
    {
        $glavpunktApi = new shippingGpshippingApi($this);

        $params = array(
            'cityFrom' => $this->cityFrom,
            'cityTo' => $this->getAddress('city')
        );
        $punkts = $glavpunktApi->getPunkts($params);

        $weight = $this->getTotalWeight() == 0 ? $this->weightDefault : $this->getTotalWeight();
        $price = $this->getTotalPrice();
        $data = array();

        foreach ($punkts as $k => $v) {
            $data[$v['id']] = array(
                'serv' => 'выдача',
                'cityFrom' => $this->cityFrom,
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

        $tarifForCity = $glavpunktApi->getTarifForCity($params);

        if (isset($tarifForCity['tarifRange'])) {
            $tarifForCity = $glavpunktApi->getTarifsForCity($data);

            foreach ($tarifForCity as $kTarif => $vTarif) {
                foreach ($punkts as $k => $v) {
                    if ($kTarif == $k) {
                        $punkts[$k]['tarif'] = $vTarif['tarif'];
                    }
                }
            }
        } else {
            foreach ($punkts as $k => $v) {
                $punkts[$k]['tarif'] = $tarifForCity['tarif'];
            }
        }

        foreach ($punkts as $k => $v) {

            $additional = (isset($v['email']) && $v['email'] != '' ? 'Email: ' . $v['email'] . '; ' : '');
            $additional .= (isset($v['phone']) && $v['phone'] != '' ? 'Телефон: ' . $v['phone'] . '; ' : '');

            $deliveries[$v['id']] = array(
                'name' => 'Пункт выдачи ' . (isset($v['metro']) ? $v['metro'] : $v['address']), //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                'est_delivery' => $this->periodDelivery($v['delivery_period'], '0'), //произвольная строка, содержащая  информацию о примерном времени доставки
                'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                'rate' => $this->finalTarif($v['tarif'], 'pickup'), //точная стоимость доставки
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

    /**
     * Метод возвращает массив для типа доставки post
     *
     * @param string $cityTo город назначения
     * @return array
     * @throws waException
     */
    private function getArrayPost()
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
                'fullRko' => true,
                'price' => $this->getTotalPrice()
            );

            $url = '/api/get_pochta_tarif?' . http_build_query($params);
            $tarif = (new shippingGpshippingApi($this))->request($url);

            $cost = $this->finalTarif($tarif['tarifTotal'], 'post');
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

    /**
     * Метод возвращает массив для типа доставки todoor
     *
     * @param string $cityTo город назначения
     * @return array
     * @throws waException
     */
    private function getArrayTodoor()
    {
        $weight = $this->getTotalWeight() == 0 ? $this->weightDefault : $this->getTotalWeight();

        $params = array(
            'cityFrom' => $this->cityFrom,
            'cityTo' => $this->getAddress('city'),
            'serv' => 'курьерская доставка',
            'paymentType' => 'cash',
            'weight' => $weight,
            'fullRko' => true,
            'price' => $this->getTotalPrice()
        );

        $url = '/api-1.1/get_tarif?' . http_build_query($params);
        $tarif = (new shippingGpshippingApi($this))->request($url);

        $estDelivery = $this->periodDelivery($tarif['period'], $this->daysForCourier);

        return $todoor = array(
            'name' => 'Курьерская доставка Главпункт', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
            'est_delivery' => $estDelivery, //произвольная строка, содержащая  информацию о примерном времени доставки
            'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
            'rate' => $this->finalTarif($tarif['tarif'], 'todoor'), //точная стоимость доставки
            'type' => waShipping::TYPE_TODOOR, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
            'service' => 'Главпункт', //название службы доставки для указания компании, выполняющей фактическую доставку
        );
    }

    /**
     * Set package state into waShipping::STATE_READY
     * Вызывается при смене статуса заказа на отправлен
     * @param waOrder $order
     * @param array $shipping_data
     * @return null|string|string[] null, error or shipping data array
     * @throws waException
     */
    protected function readyPackage(waOrder $order, $shipping_data = array())
    {
        try {
            return (new shippingGpshippingCreatedOrder($this))->create($order);
        } catch (waException $ex) {
            return "Заказ не был создан в системе Главпункт по причине: " . $ex->getMessage() .
                ". Необходимо создать данный заказ вручную в ЛК Главпункт.";
        }
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
                'zip' => array('cost' => true, 'required' => true),
                'street' => array('required' => true),
                'city' => array('required' => true)
            );
        }
    }

    /**
     * Создание дополнительного поля
     *
     * @param waOrder $order
     * @param array $service
     * @return array
     */
    public function customFieldsForService(waOrder $order, $service)
    {
        if ($service['type'] == 'todoor') {
            $fields = parent::customFieldsForService($order, $service);

            $setting = $this->getSettings('customer_interval');

            if (!empty($setting['interval']) || !empty($setting['date'])) {
                if (!strlen($this->delivery_time)) {
                    $from = time();
                } else {
                    $from = strtotime(preg_replace('@,.+$@', '', $this->delivery_time));
                }
                $offset = max(0, ceil(($from - time()) / (24 * 3600)));
                $shipping_params = $order->shipping_params;
                $value = array();

                if (!empty($shipping_params['desired_delivery.interval'])) {
                    $value['interval'] = $shipping_params['desired_delivery.interval'];
                }
                if (!empty($shipping_params['desired_delivery.date_str'])) {
                    $value['date_str'] = $shipping_params['desired_delivery.date_str'];
                }
                if (!empty($shipping_params['desired_delivery.date'])) {
                    $value['date'] = $shipping_params['desired_delivery.date'];
                }

                $fields['desired_delivery'] = array(
                    'value' => $value,
                    'title' => 'Желаемая дата доставки',
                    'control_type' => waHtmlControl::DATETIME,
                    'params' => array(
                        'date' => empty($setting['date']) ? null : ifempty($offset, 0),
                        'interval' => ifset($setting['interval']),
                        'intervals' => ifset($setting['intervals']),
                    ),
                );
            }

            return $fields;
        }

        return array();
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
        return "$num " . ($num >= 5
                ? 'дней'
                : ($num > 1 ? 'дня' : 'день')
            );
    }

    /**
     * Возвращает итоговый тариф доставки
     *
     * @param string $tarif
     * @param string $typeDelivery $this->getAddress('city')
     * @return integer
     * @throws waException
     */
    private function finalTarif($tarif, $typeDelivery)
    {
        $finalTarif = (float)$tarif;

        switch ($typeDelivery) {
            case 'post':
                $finalTarif += (float)$this->markupPost;
                break;
            case 'todoor':
                if ($this->getAddress('city') == 'Москва') {
                    $finalTarif = $this->correctTarif($tarif, $this->freeShippingMSK, $this->fixedShippingMSK, $this->markupTodoorMSK);
                } elseif ($this->getAddress('city') == 'Санкт-Петербург') {
                    $finalTarif = $this->correctTarif($tarif, $this->freeShippingSPB, $this->fixedShippingSPB, $this->markupTodoorSPB);
                } else {
                    $finalTarif = $this->correctTarif($tarif, '', '', $this->markupTodoorCommon);
                }
                break;
            case 'pickup':
                if ($this->getAddress('city') == 'Москва') {
                    $finalTarif = $this->correctTarif($tarif, $this->freeShippingMSK, $this->fixedShippingMSK, $this->markupPickupMSK);
                } elseif ($this->getAddress('city') == 'Санкт-Петербург') {
                    $finalTarif = $this->correctTarif($tarif, $this->freeShippingSPB, $this->fixedShippingSPB, $this->markupPickupSPB);
                } else {
                    $finalTarif = $this->correctTarif($tarif, '', '', $this->markupPickupCommon);
                }
                break;
            default:
                throw new waException('Неизвестный тип доставки');
        }

        return number_format($finalTarif, 2);
    }

    /**
     * Применение настроек для тарифа по заданным параметрам фиксированного тарифа, наценки и пр
     *
     * @param float $tarif
     * @param string $freeFrom
     * @param string $fixedTarif
     * @param string $markup
     * @return mixed
     */
    private function correctTarif($tarif, $freeFrom, $fixedTarif, $markup)
    {
        if ($freeFrom !== null && $freeFrom != '' && $freeFrom <= $this->getTotalPrice()) {
            return 0;
        } elseif ($fixedTarif !== null && $fixedTarif != '') {
            return $fixedTarif;
        } else {
            return $tarif + (float)$markup;
        }
    }

    /**
     * Возвращает массив с доступными пунктами отгрузки
     *
     * @return array
     * @throws waException
     */
    public static function punktList()
    {
        $punkts = (new shippingGpshippingApi)->punktList();
        $data = array();

        foreach ($punkts as $punkt) {
            array_push($data, array('value' => $punkt['id'], 'title' => $punkt['metro'], 'description' => ''));
        }

        return $data;
    }

    ###############
    # MAKE PUBLIC #
    ###############

    public function getTotalWeight()
    {
        return parent::getTotalWeight();
    }

    public function getAddress($field = null)
    {
        return parent::getAddress($field);
    }
}
