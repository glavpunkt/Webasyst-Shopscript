<?php

/**
 * Класс модуля доставки
 *
 * Class gpsgippingShippingApi
 * @author SokolovMikhail
 */
class gpsgippingShippingApi
{
    protected $gp = null;

    public function __construct(gpshippingShipping $gpShipping = null)
    {
        $this->gp = $gpShipping;
    }

    /**
     * Собирает параметры для выгрузи в ЛК Главпункт и отправляет
     *
     * @param array $data
     * @param string $sku
     * @return array
     * @throws waException
     */
    public function createShipment($data, $sku)
    {
        $url = 'https://glavpunkt.ru/api/create_shipment';

        $answer = $this->request($url, $data);

        if ($answer['result'] == 'error') {
            throw new waException($answer['message']);
        } else {
            $trackcode = $answer['pkgs'][$sku]['track_code'];
            return array(
                'order_id' => $trackcode,
                'status' => 'none',
                'view_data' => "Cоздан заказ в накладной " . $answer['docnum'] . " с треккодом $trackcode ",
                'tracking_number' => $trackcode,
            );
        }
    }

    /**
     * Возвращает массив с доступными пунктами отгрузки
     *
     * @return array
     * @throws waException
     */
    public function punktList()
    {
        $data = array();
        $punkts = $this->request('http://glavpunkt.ru/api/punkts/priemka');

        foreach ($punkts as $punkt) {
            array_push($data, array('value' => $punkt['id'], 'title' => $punkt['metro'], 'description' => ''));
        }

        return $data;
    }

    /**
     * Возвращает массив со всеми пвз в городе доставки и ценой
     *
     * @param string $cityTo название города назначения
     * @param string $cityFrom название город отправки из настроек
     * @return array
     * @throws waException
     */
    public function punkts($cityTo, $cityFrom)
    {
        $params = array(
            'cityFrom' => $this->gp->cityFrom,
            'cityTo' => $this->gp->getAddress('city')
        );

        $punkts = $this->request('https://glavpunkt.ru/api/pvz_list?' . http_build_query($params));

        $weight = $this->gp->getTotalWeight() == 0 ? $this->gp->weightDefault : $this->gp->getTotalWeight();
        $price = $this->gp->getTotalPrice();
        $data = array();

        foreach ($punkts as $k => $v) {
            $data[$v['id']] = array(
                'serv' => 'выдача',
                'cityFrom' => $cityFrom,
                'cityTo' => $cityTo,
                'weight' => $weight,
                'price' => $price,
                'punktId' => $v['id'],
                'paymentType' => 'cash'
            );
        }

        $params = array(
            'cityFrom' => $this->gp->cityFrom,
            'cityTo' => $this->gp->getAddress('city'),
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

    /**
     * Выполнение запроса
     *
     * @param string $url
     * @param array $data массив параметров для передачи POST запросом
     * @return array
     * @throws waException
     */
    public function request($url, $data = null)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (!is_null($data)) {
            $encodeData = json_encode($data);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encodeData);
            curl_setopt($curl, CURLOPT_POST, true);
        }

        $out = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($out, true);

        if (is_null($res)) {
            throw new waException('Неверный JSON ответ: ' . $out);
        }

        return $res;
    }
}
