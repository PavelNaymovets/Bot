<?php
    
    /**
     * Телеграм бот "ReSupp_bot".
     * 
     * Описание бота: бот содержит навигацию по pet-проектам и задачам из обучения, 
     *                которые не вошли в резюме.
     * 
     * Что может этот бот: 1. Отправить сообщение пользователю, инлайн клавиатуру(клавиатура под сообщением) 
     *                     со ссылками на проекты на GitHub, картинку с котом;
     *                     2. Принять картинку от пользователя, сохранить её на сервере;
     *                     3. Предложить оценить бота;
     *                     4. Отправить результат оценки на почту создателю бота;
     *                     5. Сохранить результат оценки в БД.
     * 
     * Ссылка: https://t.me/telesample1092_bot
    */
    
    // $ReSupp_bot->chat_sendMessage('привет от бота телеграм');
    
    /* ПОДКЛЮЧЕНИЕ КЛАССА РАБОТЫ С БОТОМ */
    require_once 'MyBot.php';
    
    /* ЗАДАНИЕ ОСНОВНЫХ КОНСТАНТ */
    const BOT_TOKEN = '5777734619:AAF1cp34ez6BphAOM3VSFjmErLITcKJOml0';
    
    /* ПОЛУЧЕНИЕ СООБЩЕНИЙ ИЗ ЧАТА В ВИДЕ АССОЦИАТИВНОГО МАССИВА*/
    $ReSupp_bot = new MyBot(BOT_TOKEN);
    $arrDataAnswer = $ReSupp_bot->getDataFromChat();
    
    /* ОБРАБОТКА ТЕКСТОВЫХ СООБЩЕНИЙ ИЗ ЧАТА */
    if(!empty($arrDataAnswer['message'])) {
        $textMessage = mb_strtolower($arrDataAnswer['message']['text']);
        if($textMessage == '/start') {
            $ReSupp_bot->sendStartInlineButton();
        }
    }
    
    /* СОХРАНЕНИЕ ФОТО (ДОКУМЕНТА ФОТО) ИЗ ЧАТА */
    if((!empty($arrDataAnswer['message']['photo'])) || (!empty($arrDataAnswer['message']['document']))) {
        $ReSupp_bot->savePhotoOnServer();
        $ReSupp_bot->chat_sendMessage('Отличное фото! Я его сохраню.');
    }
    
    /* ОБРАБОТКА НАЖАТИЯ КНОПОК INLINE КЛАВИАТУРЫ */
    if(!empty($arrDataAnswer['callback_query'])) {
        $dataButton = $arrDataAnswer['callback_query']['data'];
        
        $ReSupp_bot->startButtonHandler();
        $ReSupp_bot->otherButtonHandler();
        $ReSupp_bot->rateButtonHandler();
    }

?>