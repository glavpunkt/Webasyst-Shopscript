<?php

/**
 * Класс модуля доставки
 *
 * Отвечает за расчёт цены
 *
 * Class glavpunktpointsShipping
 * @author SergeChepikov
 */
class glavpunktpostShipping extends waShipping
{
    /**
     * Расчёт стоимости доставки и вывод её
     *
     * @return array
     */
    protected function calculate()
    {
        $address = trim(
            $this->getAddress('zip') . " " . $this->getAddress('city') . " " . $this->getAddress('street')
        );
        $city = trim($this->getAddress('city'));
        $street = trim($this->getAddress('street'));
        $zip = trim($this->getAddress('zip'));
        // Проверяем, заполнено ли поля адреса и индекса
        if (($city === '' || $street === '') && $zip === '') {
            return array(
                array(
                    'rate' => null,
                    'comment' => 'Введите адрес доставки или индекс',
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

        $this->getAddress('zip');

        // Формируем URL запроса в ГП для получение тарифа
        $url = 'https://glavpunkt.ru/api/get_pochta_tarif' .
            '?address=' . $address .
            '&cityFrom =' . ($cityFrom !== 'Москва' ? "MSK" : "SPB") .
            '&cms=shopscript' .
            '&weight=' . $this->getTotalWeight() .
            '&price=' . $this->getTotalPrice();

        $url .= $zip !== '' ? '&index=' . $zip : '';

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

        return array(
            'gpPost' => array(
                //название варианта доставки
                'name' => 'Почта РФ Главпункт',
                //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                'currency' => $this->currency,
                //точная стоимость доставки
                'rate' => $answer['tarifTotal'],
            )
        );
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
            'zip' => array(),
            'city' => array(),
            'street' => array(),
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
}
