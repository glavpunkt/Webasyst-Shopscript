<?php

/**
 * Класс модуля доставки
 *
 * Отвечает за расчёт цены
 *
 * Class glavpunktpointsShipping
 * @author SergeChepikov
 */
class glavpunktcourierShipping extends waShipping
{
    /** @var int количество дней, через которое можно выбирать дату доставки */
    private $days = 1;

    /**
     * Расчёт стоимости доставки и вывод её
     *
     * @return array
     */
    protected function calculate()
    {
        // Проверяем, заполнено ли поле "Город"
        if (trim($this->getAddress('city')) === '') {
            return array(
                array(
                    'rate' => null,
                    'comment' => 'Введите город доставки',
                )
            );
        }

        // Проверяем вес заказа
        if ($this->getTotalWeight() > 20) {
            return array(
                array(
                    'rate' => null,
                    'comment' => 'Данная доставка недоступна для заказов весом более 20 кг',
                )
            );
        }

        // Проверяем параметр "Город отправки"
        $cityFrom = $this->getSettings('cityFrom') !== ''
            ? $this->getSettings('cityFrom')
            : 'Санкт-Петербург';

        // Формируем URL запроса в ГП для получение тарифа
        $url = 'https://glavpunkt.ru/api/get_tarif' .
            '?serv=' . 'курьерская доставка' .
            '&cms=shopscript' .
            '&cityFrom=' . $cityFrom .
            '&cityTo=' . $this->getAddress('city') .
            '&weight=' . $this->getTotalWeight() .
            '&price=' . $this->getTotalPrice();

        // Получение ответа от ГП
        $answer = $this->request($url);

        // Проверка на ошибку при запросе
        if ($answer['result'] === 'error') {
            return array(
                array(
                    'rate' => null,
                    'comment' => $answer['message'],
                )
            );
        }

        // Установка количества дней, через которое можно выбирать дату доставки,
        // в зависимости от минимального периода доставки
        $this->days = ($this->GetIntervalFromPeriod($answer['period']))['minDays'];

        return array(
            'gpCoutier' => array(
                //название варианта доставки
                'name' => 'Курьерская доставка',
                //произвольная строка, содержащая  информацию о примерном времени доставки
                'est_delivery' => ($this->GetIntervalFromPeriod($answer['period']))['description'],
                //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                'currency' => $this->currency,
                //точная стоимость доставки
                'rate' => $answer['tarif'],
            )
        );
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
     * Возвращает массив полей формы запроса адреса доставки, которые должны запрашиваться у покупателя
     * во время оформления заказа.
     *
     * @see waShipping::requestedAddressFields()
     * @example <pre>return array(
     *     // поле запрашивается
     *     'zip'     => array(),
     *
     *     // скрытое поле с указанным вручную значением
     *     'country' => array('hidden' => true, 'value' => 'rus', 'cost' => true),
     *
     *     // параметр 'cost' означает, что значение данного поля используется для
     *      предварительного расчета стоимости доставки
     *     'region'  => array('cost' => true),
     *     'city'    => array(),
     *
     *     // поле не запрашивается
     *     'street'  => false,
     * );</pre>
     * @return array|bool Верните false, если плагин не длолжен запрашивать адрес доставки;
     * верните пустой array(), если все поля адреса должны запрашиваться у покупателя
     */
    public function requestedAddressFields()
    {
        return array(
            'city' => array('cost' => true),
            'street'  => array('cost' => true),
        );
    }

    /**
     * Пользовательские поля
     *
     * @param waOrder $order
     * @return mixed
     */
    public function customFields(waOrder $order)
    {
        $fields = parent::customFields($order);

        $this->registerControl('CustomDeliveryIntervalControl');

        // Доступные интервалы доставки
        $setting = array(
            'interval' => true,
            'date' => true,
            'intervals' => array(
                array(
                    'from' => 10,
                    'from_m' => 00,
                    'to' => 18,
                    'to_m' => 00,
                    'day' => array(1, 1, 1, 1, 1, 1),
                ),
                array(
                    'from' => 10,
                    'from_m' => 00,
                    'to' => 14,
                    'to_m' => 00,
                    'day' => array(1, 1, 1, 1, 1),
                ),
                array(
                    'from' => 11,
                    'from_m' => 00,
                    'to' => 14,
                    'to_m' => 00,
                    'day' => array(1, 1, 1, 1, 1),
                ),
                array(
                    'from' => 12,
                    'from_m' => 00,
                    'to' => 15,
                    'to_m' => 00,
                    'day' => array(1, 1, 1, 1, 1),
                ),
                array(
                    'from' => 13,
                    'from_m' => 00,
                    'to' => 16,
                    'to_m' => 00,
                    'day' => array(1, 1, 1, 1, 1),
                ),
                array(
                    'from' => 14,
                    'from_m' => 00,
                    'to' => 17,
                    'to_m' => 00,
                    'day' => array(1, 1, 1, 1, 1),
                ),
                array(
                    'from' => 15,
                    'from_m' => 00,
                    'to' => 18,
                    'to_m' => 00,
                    'day' => array(1, 1, 1, 1, 1),
                ),
            )
        );

        if (!strlen($this->delivery_time)) {
            $from = time();
        } else {
            $from = strtotime(preg_replace('@,.+$@', '', $this->delivery_time));
        }
        $offset = max($this->days, round(($from - time()) / (24 * 3600)));
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
            'title' => 'Желаемое время доставки',
            'control_type' => 'CustomDeliveryIntervalControl',
            'params' => array(
                'date' => empty($setting['date']) ? null : ifempty($offset, 0),
                'interval' => ifset($setting['interval']),
                'intervals' => ifset($setting['intervals']),
            ),
        );

        return $fields;
    }

    /**
     * Инициализация и отправка cURL запроса
     *
     * @param $url
     * @return array
     */
    private function request($url)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url
        ));
        $answer = json_decode(curl_exec($curl), true);
        curl_close($curl);

        return $answer;
    }


    /**
     * Эта функция для добаления окончания слова день по числу
     *
     * @param $dayNumber
     * @return string
     */
    private function SetDaysEnd($dayNumber)
    {
        return ($dayNumber >= 5
            ? "дней"
            : ($dayNumber > 1 ? "дня" : "день")
        );
    }

    /**
     * Мы получаем период в виде строки и получаем массив с днями доставки
     *
     * @param $period
     * @return array
     */
    private function GetIntervalFromPeriod($period)
    {
        preg_match_all('/\d+/', $period, $match);
        if (count($match[0]) == 1) {
            $min_days = $match[0][0];
            $description = "$min_days " . $this->SetDaysEnd($min_days);
        } elseif (count($match[0]) > 1) {
            $min_days = $match[0][0];
            $max_days = $match[0][1];
            $description = "от $min_days до $max_days дней";
        }

        return array(
            'description' => $description,
            'minDays' => $min_days
        );
    }
}
