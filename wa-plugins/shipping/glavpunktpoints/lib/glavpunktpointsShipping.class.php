<?php

/**
 * Класс модуля доставки
 *
 * Отвечает за расчёт цены
 *
 * Class glavpunktpointsShipping
 * @author SergeChepikov
 */
class glavpunktpointsShipping extends waShipping
{
    /**
     * Расчёт стоимости доставки и вывод её
     *
     * @return array
     * [
     *  // идентификатор варианта доставки
     *  'punkts' => [
     *      // название варианта доставки
     *      'name' => 'Вариант доставки',
     *      // валюта, в которой проводился расчёт
     *      'currency' => 'RUB',
     *      // произвольная строка, содержащая  информацию о примерном времени доставки
     *      'est_delivery' => 'от 2 до 5 дней',
     *      // точная стоимость доставки
     *      'rate' => $answer['tarif']
     *  ]
     * ]
     */
    protected function calculate()
    {
        $keyPunktId = $this->getCacheKey('punktId');
        $keyCity = $this->getCacheKey('city');
        $keyText = $this->getCacheKey('text');
        $storage = wa()->getStorage();
        $punktId = $storage->get($keyPunktId);
        $cityTo = $storage->get($keyCity);
        $text = $storage->get($keyText);

        if (trim($punktId) === '') {
            return array(
                array(
                    'rate' => null,
                    'comment' => 'Выберите пункт выдачи'
                )
            );
        }

        // Проверяем вес заказа
        if ($this->getTotalWeight() > 20) {
            return array(
                array(
                    'rate' => null,
                    'comment' => 'Данная доставка недоступна для заказов весом более 20 кг'
                )
            );
        }

        // Проверяем параметр "Город отправки"
        $cityFrom = $this->getSettings('cityFrom') !== ''
            ? $this->getSettings('cityFrom')
            : 'Санкт-Петербург';

        // Формируем URL запроса в ГП для получение тарифа
        $url = 'https://glavpunkt.ru/api/get_tarif' .
            '?serv=' . 'выдача' .
            '&cms=shopscript' .
            '&cityFrom=' . $cityFrom .
            '&cityTo=' . $cityTo .
            '&punktId=' . $punktId .
            '&weight=' . $this->getTotalWeight() .
            '&price=' . $this->getTotalPrice();

        // Получение ответа от ГП
        $answer = $this->request($url);

        // Проверка на ошибку при запросе
        if ($answer['result'] === 'error') {
            return array(
                array(
                    'rate' => null,
                    'comment' => $answer['message']
                )
            );
        }
        $interval = $this->getIntervalFromPeriod($answer['period']);

        return array(
            'punkts' => array(
                'name' => $text,
                'currency' => $this->currency,
                // произвольная строка, содержащая  информацию о примерном времени доставки
                'est_delivery' => $interval['description'],
                // точная стоимость доставки
                'rate' => $answer['tarif']
            )
        );
    }

    /**
     * Пользовательские поля
     *
     * @param waOrder $order
     * @return array
     * [
     *  'shipping_param' => [
     *      // значение по умолчанию
     *      'value' => 'Значение поля по умолчанию'
     *      // Название поля
     *      'title' => 'Название поля'
     *      // идентификатор элемента управления
     *      'control_type' => 'MySetPrice'
     *  ]
     * ]
     */
    public function customFields(waOrder $order)
    {
        $fields = parent::customFields($order);
        $this->registerControl('MySetPrice', array($this, 'mySetPrice'));
        $fields['choosePunkt'] = array(
            'value' => "",
            'title' => 'Пункт выдачи: ',
            'control_type' => 'MySetPrice'
        );

        $fields['selectedPunkt'] = array(
            'value' => '',
            'title' => 'Выбранный пункт выдачи',
            'control_type' => waHtmlControl::HIDDEN
        );

        return $fields;
    }

    /**
     * Контроллер, который сохраняет в кэш выбранный пункт выдачи для дальнейшего расчёта
     */
    public function rateAction()
    {
        $keyPunktId = $this->getCacheKey('punktId');
        $keyCity = $this->getCacheKey('city');
        $keyText = $this->getCacheKey('text');
        $storage = wa()->getStorage();
        $storage->set($keyPunktId, waRequest::post('punktId'));
        $storage->set($keyCity, waRequest::post('city'));
        $storage->set($keyText, waRequest::post('text'));
        echo json_encode(array(
            'status' => 'ok'
        ));
    }

    /**
     * Данный метод является обработчиком для пользовательского поля selectedPunkt
     *
     * В данном мы выводим скрипт, который выводит карту и перегружает значения
     *
     * @return string
     */
    public function mySetPrice()
    {
        // получение URL по которому можно будет обрабиться к методу rateAction()
        $url_params = array(
            'action_id' => 'rate',
            'plugin_id' => $this->key,
        );
        $url = wa()->getRouteUrl(
            sprintf('%s/frontend/shippingPlugin', $this->app_id),
            $url_params,
            true
        );

        $appKey = $this->key;
        $script = <<<EOD
<a href="javascript:void(0)" onclick="glavpunkt.openMap(selectPunkt);">
    Выбрать пункт выдачи
</a>
<p class="currentPunkt"></p>
        
 <script type="text/javascript">
    /* Скрипт добавляющий в head виджет Главпункта с пунктами выдачи */
    var script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'https://glavpunkt.ru/js/punkts-widget/glavpunkt.js';
    document.head.appendChild(script);
</script>        
<script type="text/javascript">
    // Функция для обновления данных на странице
    function SetInfoAndPrice(punktInfo, priceInfo, text){
        var serviceDOM = $('.shipping-{$appKey}');
        serviceDOM.find('.price').html(priceInfo.rate_html);
        serviceDOM.find('.est_delivery').html(priceInfo.est_delivery);
        serviceDOM.find('input[name="rate_id[{$appKey}]"]').val(priceInfo.id);
        serviceDOM.find('.hint').show();
        serviceDOM.find('.error').hide();        
        $('input[name="shipping_{$appKey}[selectedPunktId]"').val(punktInfo.id); 
        
        $('input[name="shipping_{$appKey}[selectedPunkt]"').val(text);
        $('.currentPunkt').html(text);
    }
    // Функция срабатывающее на событие: выбор пункта выдачи на карте
    function selectPunkt(punktInfo) {  
        var serviceDOM = $('.shipping-{$appKey}');
        serviceDOM.find('.price').html('<i class="icon16 loading"></i>');  
        var text = 'Пункт выдачи ' + punktInfo.brand + '<br>' +
            'Город: ' + punktInfo.city + '<br>' +
            'Адрес: ' + punktInfo.address + '<br>' +
            'Название пункта: ' + punktInfo.name + '<br>' +
            'Время работы: ' + punktInfo.work_time + '<br>' +
            'Телефон: ' + punktInfo.phone + '<br>';
        // мы обращаемся к нашему методу rateAction() для сохранения пункта
        $.ajax({
            "type": 'POST',
            "url": '{$url}',
            "data": { "punktId": punktInfo.id, "city": punktInfo.city, "text": text },
            "success": function (response) {
                // обращаемся за расчётом цены доставки
                $.ajax({
                    "type": 'POST',
                    "url": '/shop/data/shipping/',
                    "data": {
                        "shipping_id": $appKey,
                        "shipping_{$appKey}[selectedPunktId]": punktInfo.id,
                        "shipping_{$appKey}[selectedCity]": punktInfo.city,
                        "shipping_{$appKey}[selectedPunkt]": "",            
                    },
                    "success": function (response) {
                        SetInfoAndPrice(punktInfo, response.data[0], text);
                    }
                });
            }
        });        
    }
</script>
EOD;

        return $script;
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
     * Инициализация и отправка cURL запроса GET
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
     * Эта фуккция для доваления окончания слова день по числу
     *
     * @param $dayNumber
     * @return string
     */
    private function setDaysEnd($dayNumber)
    {
        return ($dayNumber >= 5
            ? "дней"
            : ($dayNumber > 1 ? "дня" : "день")
        );
    }

    /**
     * Мы получаем период в виде строки и получаем массив с заполненым днями доставки
     *
     * @param $period
     * @return array
     */
    private function getIntervalFromPeriod($period)
    {
        preg_match_all('/\d+/', $period, $match);
        if (count($match[0]) == 1) {
            $min_days = $match[0][0];
            $description = "$min_days " . $this->setDaysEnd($min_days);
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

    /**
     * Получить ключ, для извлечения информации из кэша.
     * @param null $key
     * @return string
     */
    private function getCacheKey($key = null)
    {
        return sprintf(
            'wa-plugins/shipping/russianpost/%s/%s/%s',
            $this->app_id,
            $this->key,
            $key ? $key : $this->cache_key
        );
    }
}
