<?php

/**
 * Создание заказа в ЛК Главпункт
 *
 * @author SergeChepikov
 */
class shippingGpshippingCreatedOrder
{
    private $shipping;
    private $api;

    public function __construct(gpshippingShipping $shipping)
    {
        $this->shipping = $shipping;
        $this->api = new shippingGpshippingApi($shipping);
    }

    /**
     * Создание заказа в ЛК Главпункт
     *
     * @param waOrder $order
     * @return array
     */
    public function create(waOrder $order)
    {
        $orderRequest = $this->commonOrderRequest($order);

        switch ($order->params['shipping_rate_id']) {
            case 'courier':
                $orderRequest = $this->courierOrderRequest($orderRequest, $order);
                break;
            case 'post':
                $orderRequest = $this->postOrderRequest($orderRequest, $order);
                break;
            default:
                $orderRequest = $this->pickupOrderRequest($orderRequest, $order);
                break;
        }

        $request = array(
            'shipment_options' => $this->createShipmentOptions(),
            'orders' => array($orderRequest),
        );
        $answer = $this->api->createShipment($request);
        $trackcode = $answer['pkgs'][$this->sku($order)]['track_code'];

        return array(
            'order_id' => $trackcode,
            'status' => 'none',
            'view_data' => "Cоздан заказ в накладной " . $answer['docnum'] . " с треккодом $trackcode ",
            'tracking_number' => $trackcode,
        );
    }

    /**
     * Собирает общий массив общих данных для каждого заказа
     *
     * @param waOrder $order
     * @return array
     */
    private function commonOrderRequest(waOrder $order)
    {
        return array(
            'sku' => $this->sku($order),
            'price' => is_null($order->paid_datetime) ? $order->total : 0,
            'buyer_phone' => ifempty($order->shipping_address['phone'], $order->getContactField('phone')),
            'buyer_fio' => ifempty($order->shipping_address['lastname'], $order->getContactField('lastname'))
                . ' ' . ifempty($order->shipping_address['firstname'], $order->getContactField('firstname')),
            'buyer_email' => ifempty($order->shipping_address['email'], $order->getContactField('email')),
            'insurance_val' => $order->subtotal, // Оценочная (страховая) стоимость заказа
            'weight' => $this->shipping->getTotalWeight() == 0
                ? $this->shipping->weightDefault
                : $this->shipping->getTotalWeight(),// Общий вес заказа в кг.
            'parts' => $this->createParts($order)
        );
    }

    /**
     * Собирает массив с данными для выгрузки заказа курьерской доставкой
     *
     * @param array $data
     * @param waOrder $order
     * @return array
     */
    private function courierOrderRequest(array $data, waOrder $order)
    {
        $deliveresTime = explode('-', $order->shipping_params['desired_delivery.interval']);
        $data['serv'] = 'курьерская доставка';
        $data['delivery'] = array( // Параметры курьерской доставки
            "city" => $this->shipping->getAddress('city'), // Кладр города (или "SPB" или "Санкт-Петербург").
            "address" => $this->shipping->getAddress('street'),
            "date" => $order->shipping_params['desired_delivery.date'],
            "time_from" => $deliveresTime[0],
            "time_to" => $deliveresTime[1]
        );

        return $data;
    }

    /**
     * Собирает массив с данными для выгрузки заказа почтой
     *
     * @param array $data
     * @param waOrder $order
     */
    private function postOrderRequest(array $data, waOrder $order)
    {
        $adrress = $this->shipping->getAddress('zip') . ', ' .
            $this->shipping->getAddress('city') . ', ' . $this->shipping->getAddress('street');
        $data['serv'] = 'почта';
        $data['pochta'] = array(
            'address' => $adrress,
            'index' => $this->shipping->getAddress('zip')
        );

        return $data;
    }

    /**
     * Собирает массив с данными для выгрузки заказа пвз
     *
     * @param array $data
     * @param waOrder $order
     * @return array
     */
    private function pickupOrderRequest(array $data, waOrder $order)
    {
        $data['serv'] = 'выдача';
        $data['pvz_id'] = $order->shipping_rate_id;

        return $data;
    }

    /**
     * Метод создания блока shipment_options
     *
     * @return array
     */
    private function createShipmentOptions()
    {
        switch ($this->shipping->methodDelivery) {
            case 'self_delivery':
                return array(
                    'method' => $this->shipping->methodDelivery,
                    'punkt_id' => $this->shipping->selectedPunkt
                );
                break;
            case 'pickup':
                return array(
                    'method' => $this->shipping->methodDelivery,
                    'pickup_city' => $this->shipping->cityFrom, // Кладр города (или 'SPB' или 'Санкт-Петербург').
                    // Дата забора в формате 'Y-m-d'. Должна быть не раньше завтрашнего дня
                    'pickup_date' => (new DateTime('tomorrow'))->format('Y-m-d'),
                    'pickup_interval' => '10-18', // Интервал забора
                    'pickup_address' => $this->shipping->pickupAddress
                );
                break;
            default:
                throw waException('Неизвестный тип отгрузки: ' . $this->shipping->methodDelivery);
        }
    }

    /**
     * Метод создания блока с номенлатурой
     *
     * @param waOrder $order
     * @return mixed
     */
    private function createParts(waOrder $order)
    {
        foreach ($order->items as $v) {
            $data[] = array(
                'name' => $v['name'],
                'price' => $v['price'], // Сумма к получению за единицу товара
                'insurance_val' => $v['price'], // Оценочная (страховая) стоимость единицы товара
                'num' => $v['quantity'], // Количество позиций товара (по-умолчанию 1)
                'weight' => ($v['weight'] == 0 ? 1 : $v['weight'])
            );
        }

        $data[] = array(
            'name' => 'Стоимость доставки',
            'price' => $order->shipping,
            'insurance_val' => 0
        );

        return $data;
    }

    /**
     * Возвращает полный sku
     *
     * @param waOrder $order
     * @return string
     */
    private function sku(waOrder $order)
    {
        // в $order->id_str первый знак '#', его надо обрезать
        return $this->shipping->prefixId . substr($order->id_str, 1);
    }
}
