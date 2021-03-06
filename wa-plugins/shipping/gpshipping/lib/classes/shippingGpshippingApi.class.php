<?php

/**
 * Класс взаимодействия с API Главпункт
 *
 * Class shippingGpshippingApi
 * @author SokolovMikhail
 */
class shippingGpshippingApi
{
    protected $login;
    protected $token;
    private $baseUrl = 'https://glavpunkt.ru';

    public function __construct(gpshippingShipping $shipping = null)
    {
        $this->login = isset($shipping) ? $shipping->apiLogin : null;
        $this->token = isset($shipping) ? $shipping->apiToken : null;
    }

    /**
     * Собирает параметры для выгрузи в ЛК Главпункт и отправляет
     *
     * @param array $data
     * @return array
     * @throws waException
     */
    public function createShipment($data)
    {
        return $this->request('/api/create_shipment', $data);
    }

    /**
     * Возвращает массив с доступными пунктами отгрузки
     *
     * @return array
     * @throws waException
     */
    public function punktList()
    {
        return $this->request('/api/punkts/priemka');
    }

    /**
     * Возвращает массив со всеми пвз в городе доставки
     *
     * @param array $params название города назначения
     * @return array
     * @throws waException
     */
    public function getPunkts($params)
    {
       return $this->request('/api/pvz_list?' . http_build_query($params));
    }

    /**
     * Возвращает массив со всеми пвз в городе доставки и если цена одна
     *
     * @param array $params
     * @return array
     * @throws waException
     */
    public function getTarifForCity($params)
    {
        return $this->request('/api-1.1/get_tarif?' . http_build_query($params));
    }

    /**
     * Возвращает массив со всеми пвз в городе доставки и если цена разная для разных пвз
     *
     * @param array $params
     * @return array
     * @throws waException
     */
    public function getTarifsForCity($params)
    {
        return $this->request('/api-1.1/get_tarifs', $params);
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
        $curl = curl_init($this->baseUrl . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        if (!is_null($data)) {
            if (isset($this->login) && isset($this->token)) {
                $data = array_merge(
                    $data,
                    [
                        'login' => $this->login,
                        'token' => $this->token
                    ]
                );
            }

            $encodeData = json_encode($data);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encodeData);
            curl_setopt($curl, CURLOPT_POST, true);
        }

        $out = curl_exec($curl);
        curl_close($curl);
        $answer = json_decode($out, true);

        if (is_null($answer)) {
            throw new waException('Неверный JSON ответ: ' . $out);
        }

        if (isset($answer['result']) && $answer['result'] == 'error') {
            throw new waException($answer['message']);
        }

        return $answer;
    }
}
