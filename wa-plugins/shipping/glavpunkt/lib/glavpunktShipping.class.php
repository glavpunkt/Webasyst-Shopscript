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
     * Возвращает массив со всеми пвз в городе доставки без цены
     *
     * @param string $cityTo название города назначения
     * @return array
     * @throws Exception
     */
    private function getPunkts($cityTo)
    {
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
     * @param string $cityTo название города назначения
     * @param string $cityFrom название город отправки из настроек
     * @return array
     * @throws Exception
     */
    private function getPunktsWithTarif($cityTo, $cityFrom)
    {
        $punkts = $this->getPunkts($cityTo);
        $weight = $this->getTotalWeight() == 0 ? 1 : $this->getTotalWeight();
        $price = $this->getTotalPrice();
        $punktsWithTarif = array();

        foreach ($punkts as $k => $v){
            $punktsWithTarif[$v['id']] = array(
                'serv' => "выдача по РФ",
                'cityFrom' => $cityFrom,
                'cityTo' => $cityTo,
                'weight' => $weight,
                'price' => $price,
                'punktId' => $v['id'],
                'paymentType' => "cash"
            );
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

        foreach ($res as $kTarif => $vTarif) {
            foreach ($punkts as $k => $v) {
                if ($kTarif == $k) {
                    $punkts[$k]['tarif'] = $vTarif['tarif'];
                }
            }
        }

        return $punkts;
    }

    private function getArrayPickup($cityTo)
    {
        $tarifForPunktsInSelectedCity = $this->getPunktsWithTarif($cityTo, $this->cityFrom);

        foreach ($tarifForPunktsInSelectedCity as $k => $v) {

            $additional = isset($v["email"]) ? 'Email: ' . $v["email"] . '; ' : '';
            $additional .= isset($v["phone"]) ? 'Телефон: ' . $v["phone"] . '; ' : '';
            $additional .= isset($v["work_time"]) ? 'Режим работы: ' . $v["work_time"] . '; ' : '';

            $deliveries[$v['id']] = array(
                    'name' => $v["address"], //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                    'comment' => 'описание необязательно', //необязательное описание варианта доставки
                    'est_delivery' => $v["delivery_period"], //произвольная строка, содержащая  информацию о примерном времени доставки
                    'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                    'rate' => $v['tarif'], //точная стоимость доставки
                    'type' => waShipping::TYPE_PICKUP, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                    'service' => $v["operator"] != "gp" ? $v['operator'] : 'Главпункт', //название службы доставки для указания компании, выполняющей фактическую доставку
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
        return $post = array(
                'name' => 'Почта', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                'comment' => 'описание необязательно', //необязательное описание варианта доставки
                'est_delivery' => '2-3', //произвольная строка, содержащая  информацию о примерном времени доставки
                'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                'rate' => 100, //точная стоимость доставки
                'type' => waShipping::TYPE_POST, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                'service' => 'Glavpunkt', //название службы доставки для указания компании, выполняющей фактическую доставку
        );
    }

    private function getArrayTodoor($cityTo)
    {
        return $todoor = array(
                'name' => 'Курьер', //название варианта доставки, например, “Наземный  транспорт”, “Авиа”, “Express Mail” и т. д.
                'comment' => 'описание необязательно', //необязательное описание варианта доставки
                'est_delivery' => '1-2', //произвольная строка, содержащая  информацию о примерном времени доставки
                'currency' => $this->currency, //ISO3-код валюты, в которой рассчитана  стоимость  доставки
                'rate' => 101, //точная стоимость доставки
                'type' => waShipping::TYPE_TODOOR, //один из типов доставки waShipping::TYPE_TODOOR, waShipping::TYPE_PICKUP или waShipping::TYPE_POST
                'service' => 'Glavpunkt', //название службы доставки для указания компании, выполняющей фактическую доставку
        );
    }

}
