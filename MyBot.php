<?php

    /**
     * Класс описывает сущность(состояние и поведение) телеграм бота.
     * 
     * При отправке сообщений в телеграм, скрипт выполняется по новой каждый раз. 
     * Поэтому chatId и userName обновляется в зависимости от ассоциативного массива, который придет в ответ.
     * 
     * Параметры: $botToken - уникальный идентификатор бота;
     *            $baseURL - url, по которому происходит обращение к api telegram;
     *            $chatId - уникальный номер чата пользователя;
     *            $userName - имя пользователя;
     *            $messageId - уникальный номер сообщения в чате;
     *            $dataButton - содержит имя обработчика событий (callback_data);
     *            $logFileName - путь к файлу в который записываются логи.
    */
    
    class MyBot {
        private $botToken;
        private $baseURL;
        private $arrDataAnswer;
        private $chatId;
        private $userName;
        private $messageId;
        private $dataButton;
        private $logFileName = __DIR__.'/log.txt';
        
        public function __construct($botToken){
            $this->botToken = $botToken;
            $this->baseURL = "https://api.telegram.org/bot{$this->botToken}";
        }
        
        //=====================================================
        // МЕТОДЫ РАБОТЫ БОТА
        //=====================================================
        
        /**
         * ПАРАМЕТРЫ ФУНКЦИЙ:
         * 
         * $str - текстовая строка. Передавать в ' ' или в " " (если есть спец. символы);
         * $clear - чистить файл с логами перед записью данных или нет. false - нет, true - да; 
         * $method - метод отправки запроса в api telegram. Указывать без '/';
         * $arrayQuery - параметры запроса. Например текстовое сообшение, фото, файл для пользователя;
         * $text - текстовая строка. Передавать в ' ' или в " " (если есть спец. символы);
         */
        
        //=====================================================
        // ОБЩИЕ МЕТОДЫ
        //=====================================================
        
        /** 
         * ПОЛУЧЕНИЕ СООБЩЕНИЙ ИЗ ЧАТА ТЕЛЕГРАММ: 
         * 
         * return $arrDataAnswer - запрос выполнен. Ассоциативный массив с данными сообщения.
         */
        public function getDataFromChat() {
            $data = file_get_contents('php://input');
            $arrDataAnswer = json_decode($data, true);
            $this->arrDataAnswer = $arrDataAnswer;
            $this->getChatIdUserName();
            
            $this->writeToLogFile($arrDataAnswer, true); //запись данных из чата в лог файл для отладки бота
            
            return $this->arrDataAnswer;
        }
        
        /* ПОЛУЧЕНИЕ CHAT ID И USER NAME ИЗ ЧАТА */
        public function getChatIdUserName() {
            $this->chatId = $this->arrDataAnswer['message']['chat']['id'];
            $this->userName = $this->arrDataAnswer['message']['from']['username'];
            if(!empty($this->arrDataAnswer['callback_query'])) {
                $this->chatId = $this->arrDataAnswer['callback_query']['message']['chat']['id'];
                $this->userName = $this->arrDataAnswer['callback_query']['from']['username'];
                $this->messageId = $this->arrDataAnswer['callback_query']['message']['message_id'];
                $this->dataButton = $this->arrDataAnswer['callback_query']['data'];
            }
        }
            
        /* ЗАПИСЬ ДАННЫХ В log.txt */
        public function writeToLogFile($str, $clear = false) {
            if($clear == false) {
                $now = date('Y-m-d H:i:s');
                file_put_contents($this->logFileName, $now . ' ' . print_r($str, true) . "\r\n", FILE_APPEND);
            } else {
                file_put_contents($this->logFileName, ' ');
                file_put_contents($this->logFileName, $now . ' ' . print_r($str, true) . "\r\n", FILE_APPEND);
            }
        }
        
        /* СОХРАНЕНИЕ ФОТО НА СЕРВЕРЕ */
        public function savePhotoOnServer() {
            $dataFile = $this->getDataFile();
            $arrDataResult = json_decode($dataFile, true);
            $fileUrl = $arrDataResult['result']['file_path'];
            $photoPathTG = "https://api.telegram.org/file/bot{$this->botToken}/{$fileUrl}"; //формируем полный URL до файла.
            $arrFilePath = explode("/", $fileUrl);
            $newFilerPath = __DIR__ . "/img/" . $arrFilePath[1]; //забираем название файла.
            file_put_contents($newFilerPath , file_get_contents($photoPathTG)); //сохраняем файл на сервер.
        }
        
        /* ПОЛУЧЕНИЕ ДАННЫХ О ФАЙЛЕ */
        public function getDataFile() {
            if(!empty($this->arrDataAnswer['message']['photo'])) {
                $documentData = array_pop($this->arrDataAnswer['message']['photo']); //забираю последний элемент массива
            } else if(!empty($this->arrDataAnswer['message']['document']) && (($this->arrDataAnswer['message']['document']['mime_type'] == 'image/jpeg') || ($this->arrDataAnswer['message']['document']['mime_type'] == 'image/png'))) {
                $documentData = $this->arrDataAnswer['message']['document'];
            }
            $arrayQuery = array(
            	'file_id' => $documentData['file_id']
            );
            $result = $this->sendQueryToTelegram('getFile', $arrayQuery);
            
            return $result;
        }
        
        /* ПОЛУЧЕНИЕ СПИСКА ФАЙЛОВ В ПАПКЕ С ФОТОГРАФИЯМИ НА СЕРВЕРЕ */
        function listFiles($path) {
            if ($path[mb_strlen($path) - 1] != '/') {
        	    $path .= '/';
            }
         
            $files = array();
            $dh = opendir($path);
            while (false !== ($file = readdir($dh))) {
            	if ($file != '.' && $file != '..' && !is_dir($path.$file) && $file[0] != '.') {
            	    $files[] = $file;
            	}
            }
    
            closedir($dh);
            
            return $files;
        }
        
        /* ОТПРАВКА ЗАПРОСОВ В API TELEGRAM */
        public function sendQueryToTelegram($method, $arrayQuery) {
            $curl = curl_init("{$this->baseURL}/{$method}");
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $arrayQuery);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_HEADER, false);
            $result = curl_exec($curl);
            curl_close($curl);
    
            return $result;
        }
        
        //=====================================================
        // СПЕЦИАЛЬНЫЕ МЕТОДЫ. ReSupp_bot
        //=====================================================
        
        //-----------------------------------------------------
        // ОТПРАВКА ТЕКСТОВОГО СООБЩЕНИЯ, ФОТО, ДОКУМЕНТА В ЧАТ
        //-----------------------------------------------------
        
        /* ОТПРАВКА ТЕКСТОВОГО СООБЩЕНИЯ В ЧАТ */
        public function chat_sendMessage($text) {
            $arrayQuery = array(
            	'chat_id' => $this->chatId,
            	'text'	=> $text,
            	'parse_mode' => 'html',
            );
            $this->sendQueryToTelegram('sendMessage', $arrayQuery);
        }
        
        /* РЕДАКТИРОВАНИЕ ТЕКСТОВОГО СООБЩЕНИЯ В ЧАТЕ */
        public function chat_editMessage($arrayQuery) {
            $this->sendQueryToTelegram('editMessageText', $arrayQuery);
        }
        
        /* ОТПРАВКА 1-го СЛУЧАЙНОГО ФОТО В ЧАТ */
        public function chat_sendOneRandomPhoto($filePath) {
            $arrayQuery = array(
            	'chat_id' => $this->chatId,
            	'photo'	=> new CURLFile($filePath),
            	'parse_mode' => 'html',
            );
            // 'photo' => 'https://hmns.in/hmnsgc/telegramBot/img/cat.jpg', //в качестве источника можно указывать URL.
            $this->sendQueryToTelegram('sendPhoto', $arrayQuery);
        }
        
        /* ОТПРАВКА ГРУППЫ ФОТОГРАФИЙ В ЧАТ */
        public function chat_sendGroupPhoto($listFile) {
            $filePath_0 = __DIR__ . "/img/" . $listFile[0];
            $filePath_1 = __DIR__ . "/img/" . $listFile[1];
            $filePath_2 = __DIR__ . "/img/" . $listFile[2];
            $filePath_3 = __DIR__ . "/img/" . $listFile[3];
            
            $arrayQuery = array(
              "chat_id" => $this->chatId,
        	  'media' => json_encode([
            	    ['type' => 'photo', 'media' => 'attach://cat_0.jpg' ],
            	    ['type' => 'photo', 'media' => 'attach://cat_1.jpg' ],
            	    ['type' => 'photo', 'media' => 'attach://cat_2.jpg' ],
            	    ['type' => 'photo', 'media' => 'attach://cat_3.jpg' ],
                    ]),
                    'cat_0.jpg' => new CURLFile($filePath_0),
                    'cat_1.jpg' => new CURLFile($filePath_1),
                    'cat_2.jpg' => new CURLFile($filePath_2),
                    'cat_3.jpg' => new CURLFile($filePath_3),
            );
            $this->sendQueryToTelegram('sendMediaGroup', $arrayQuery);
        }
        
        /* ОТПРАВКА ДОКУМЕНТА В ЧАТ */
        public function chat_sendDocument($filePath) {
            $arrayQuery = array(
            	'chat_id' => $this->chatId,
            	'document'	=> new CURLFile($filePath),
            	'parse_mode' => 'html',
            );
            $this->sendQueryToTelegram('sendDocument', $arrayQuery);
        }
        
        
        //-----------------------------------------------------
        // ОТПРАВКА ТЕКСТОВОГО СООБЩЕНИЯ НА ПОЧТУ
        //-----------------------------------------------------
        
        /* ОТПРАВКА СООБЩЕНИЯ НА ПОЧТУ */
        public function sendMessageToEmail($rate) {
            $to = 'pavel.naumovets@mail.ru';
            $subject = 'Оценка бота';
            $message = "Пользователь {$this->userName} поставил боту оценку: {$rate}";
            $headers = array(
                'From' => 'info@TelegramBotRate.ru',
                'Reply-To' => 'webmaster@example.com',
                'X-Mailer' => 'PHP/' . phpversion()
            );
            mail($to, $subject, $message, $headers);            
        }
        
        //-----------------------------------------------------
        // КНОПКИ МЕНЮ
        //-----------------------------------------------------
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: СТАРТОВОЕ СООБЩЕНИЕ */
        public function sendStartInlineButton() {
            $textMessage = "Добрый день, $this->userName!\r\n\r\nС помощью этого бота Вы можете посмотреть pet-проекты и задания из курсов, которые не вошли в мое резюме😊\r\n\r\n📍Ниже приведен ряд кнопок, которые помогут с навигацией:\r\n\r\n<b>Главное меню:</b>\r\n\r\n📋 Pet-проекты. Бот предоставит ссылки на репозиторий на GitHub, где хранится код и описание pet-проектов (файл README)\r\n\r\n📝 Обучение. Бот предоставит список прочитанных мною книг, а также ссылки на репозиторий на GitHub, где хранится код и описание дз (файл README)\r\n\r\n💡 Прочее. Здесь содержатся дополнительные функции бота, которые описаны ниже 👇\r\n\r\n<b>Прочее:</b>\r\n\r\n🐈 Поднять настроение. Бот пришлет рандомную веселую картинку с котиком\r\n\r\n📊 Оценить бота. Бот пришлет шкалу, по которой можно оценить бота. Оценка придет мне на почту📩\r\n\r\n📷 Поделиться фото. Пришлите фото, документ фото (в формате png или jpg, не pdf) котика в чат. Бот сохранит его на сервер. Возможно именно Ваше фото поднимет настроение следующему пользователю😊\r\n\r\n<b>Бот понимает команды:</b>\r\n\r\n🔸 /start\r\n🔸 /hello\r\n🔸 /bye\r\n\r\nСпасибо, что воспользовались моим ботом и узнали дополнительную информацию обо мне!\r\n\r\nНадеюсь, информация была вам полезна👍";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => '📋 Pet-проекты',
                                     'callback_data' => 'pet'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '📝 Обучение',
                                     'callback_data' => 'study'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '💡 Прочее',
                                     'callback_data' => 'other'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->sendQueryToTelegram('sendMessage', $arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: PHP-ПРОЕКТЫ */
        public function sendPhpProjectsInlineButton() {
            $textMessage = "$this->userName, Вы в разделе Pet-проекты 📋\r\n\r\nВ этом разделе содержатся ссылки на мои домашние проекты. Код проектов и их описание (файл README) расположены на удаленном репозитории GitHub.\r\n\r\n<b>Разделы:</b>\r\n\r\n▫PHP\r\n▫Java\r\n▫HTML5, CSS3, PHP\r\n▫Задачи с собеседований\r\n\r\nНиже представлены кнопки со ссылками на проекты👇";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => 'Планировщик задач',
                                     'url' => 'https://github.com/PavelNaymovets/GC-library',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Магазин кодов Steam',
                                     'url' => 'https://github.com/PavelNaymovets/SlonikStore',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Телеграм бот',
                                     'url' => 'https://github.com/PavelNaymovets/Bot',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Фитнес трекер',
                                     'url' => 'https://github.com/PavelNaymovets/miskaJane',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Добавление заказа API GetCourse',
                                     'url' => 'https://github.com/PavelNaymovets/GetCourse',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_petMenu'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: Java-ПРОЕКТЫ */
        public function sendJavaProjectsInlineButton() {
            $textMessage = "$this->userName, Вы в разделе Pet-проекты 📋\r\n\r\nВ этом разделе содержатся ссылки на мои домашние проекты. Код проектов и их описание (файл README) расположены на удаленном репозитории GitHub.\r\n\r\n<b>Разделы:</b>\r\n\r\n▫PHP\r\n▫Java\r\n▫HTML5, CSS3, PHP\r\n▫Задачи с собеседований\r\n\r\nНиже представлены кнопки со ссылками на проекты👇";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => 'Система управления проектами',
                                     'url' => 'https://github.com/PavelNaymovets/project_management_system/tree/develop',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Простой интернет магазин',
                                     'url' => 'https://github.com/PavelNaymovets/spring_secutiry/tree/spring_practice',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Сетевой чат',
                                     'url' => 'https://github.com/PavelNaymovets/Java_Level2_NetworkChat_v2_NaumovetsPR',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Простой файловый менеджер',
                                     'url' => 'https://github.com/PavelNaymovets/Java_Level2_FileManager_NaumovetsPR',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Игра “Bulls and Cows”',
                                     'url' => 'https://github.com/PavelNaymovets/Java_Level2_BullsAndCows_NaumovetsPR/tree/BullsAndCows',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Игра “Ловля капли мышкой”',
                                     'url' => 'https://github.com/PavelNaymovets/cath_the_drop_v2',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Калькулятор',
                                     'url' => 'https://github.com/PavelNaymovets/Java_Level1_HomeWorks_NaumovetsPR/tree/homeWork8/src/main/java/ru/gb/naumovets/homeWork8',
                                     'callback_data' => 'noHandle'
                                 )
                             ),                             
                             array(
                                 array(
                                     'text' => 'Минер',
                                     'url' => 'https://github.com/PavelNaymovets/Java_Level1_HomeWorks_NaumovetsPR/tree/homeWork8/src/main/java/ru/gb/naumovets/homeWork4',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_petMenu'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: HTML,CSS-ПРОЕКТЫ */
        public function sendHtmlCssProjectsInlineButton() {
            $textMessage = "$this->userName, Вы в разделе Pet-проекты 📋\r\n\r\nВ этом разделе содержатся ссылки на мои домашние проекты. Код проектов и их описание (файл README) расположены на удаленном репозитории GitHub.\r\n\r\n<b>Разделы:</b>\r\n\r\n▫PHP\r\n▫Java\r\n▫HTML5, CSS3, PHP\r\n▫Задачи с собеседований\r\n\r\nНиже представлены кнопки со ссылками на проекты👇";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => 'Сайт зубной клиники',
                                     'url' => 'https://etalondentclinic.ru/',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Одностраничный лендинг',
                                     'url' => 'https://github.com/PavelNaymovets/VisualStudioProjects/tree/homeWork6/lesson_2%2C3%2C4%2C5%2C6/homeWork_divBlocking',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_petMenu'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: Задачи с собеседований */
        public function sendInterviewTasksProjectsInlineButton() {
            $textMessage = "$this->userName, Вы в разделе Pet-проекты 📋\r\n\r\nВ этом разделе содержатся ссылки на мои домашние проекты. Код проектов и их описание (файл README) расположены на удаленном репозитории GitHub.\r\n\r\n<b>Разделы:</b>\r\n\r\n▫PHP\r\n▫Java\r\n▫HTML5, CSS3, PHP\r\n▫Задачи с собеседований\r\n\r\nНиже представлены кнопки со ссылками на проекты👇";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => 'Решение квадратных уравнений',
                                     'url' => 'https://github.com/PavelNaymovets/interview_task_quadratic_equation',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Аналог базы данных в памяти',
                                     'url' => 'https://github.com/PavelNaymovets/interview_task_digDes',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_petMenu'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: PET-ПРОЕКТЫ */
        public function sendPetInlineButton() {
            $textMessage = "$this->userName, Вы в разделе Pet-проекты 📋\r\n\r\nВ этом разделе содержатся ссылки на мои домашние проекты. Код проектов и их описание (файл README) расположены на удаленном репозитории GitHub.\r\n\r\n<b>Разделы:</b>\r\n\r\n▫PHP\r\n▫Java\r\n▫HTML5, CSS3, PHP\r\n▫Задачи с собеседований\r\n\r\nНиже представлены кнопки со ссылками на проекты👇";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => 'PHP',
                                     'callback_data' => 'PHP_projects'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Java',
                                     'callback_data' => 'Java_projects'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'HTML5, CSS3',
                                     'callback_data' => 'HTML5_CSS3_projects'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Задачи с собеседований',
                                     'callback_data' => 'interview_projects'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_pet_mainMenu'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: ОБУЧЕНИЕ */
        public function sendStudyInlineButton() {
            $textMessage = "$this->userName, Вы в разделе обучение 📝\r\n\r\nВ этом разделе содержатся список прочитанных мною книг, а также ссылки на решение задач из курсов, которые я проходил. Решение расположено на удаленном репозитория GitHub. На репозитории хранится код решения и текст задач из курсов.\r\n\r\n▫Все задачи распределены по папкам для удобной навигации\r\n▫Каждая задача содержит описание в своей папке, в файле README\r\n\r\n<b>Курсы:</b>\r\n\r\n▫EPAM\r\n▫GeekBrains\r\n\r\n<b>Книги:</b>\r\n\r\nГрокаем алгоритмы, Код, Теоретический минимум по Computer Science, Программирование на Java для детей, родитлей... , OCA, OCP, UML основы, Хортсманн том 1(читаю сейчас), Паттерны проектирования. Банда четырех(читаю сейчас)\r\n\r\n<b>Цель в изучении технологий:</b>\r\n\r\nDocker, Docker compose, k8s, Linux";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => 'EPAM',
                                     'callback_data' => 'epam'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'GeekBrains',
                                     'callback_data' => 'geekBrains'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_study_mainMenu'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        
         /* КНОПКИ МЕНЮ. РАЗДЕЛ: EPAM */
        public function sendEpamInlineButton() {
            $textMessage = "$this->userName, Вы в разделе EPAM 📝\r\n\r\n<b>О курсе:</b>\r\n\r\n▫Длительность: 3,5 месяца\r\n▫Место: тренинговый центр EPAM\r\n▫Язык: русский, английский\r\n▫Режим: удаленный\r\n▫Формат: Видеозаписи уроков. Задачи. 1 раз в неделю вебинар с опытным разработчиком, на котором можно задавать вопросы по обучению\r\n\r\nЗадачи шли друг за другом, последовательно. Логического разделения на блоки с названиями у них не было. Поэтому снизу указаны порядковые номера задач. Ниже представлены кнопки со ссылками на задачи из курса👇";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => '1',
                                     'url' => 'https://github.com/PavelNaymovets/epam_segments',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '2',
                                     'url' => 'https://github.com/PavelNaymovets/epam_flood-fill',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '3',
                                     'url' => 'https://github.com/PavelNaymovets/epam_collections-count-words',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '4',
                                     'url' => 'https://github.com/PavelNaymovets/epam_bst-pretty-print',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '5',
                                     'url' => 'https://github.com/PavelNaymovets/epam_file-tree',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '6',
                                     'url' => 'https://github.com/PavelNaymovets/epam_hashtable-open-8-16',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '7',
                                     'url' => 'https://github.com/PavelNaymovets/epam_figures',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '8',
                                     'url' => 'https://github.com/PavelNaymovets/epam_figures-extra',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '9',
                                     'url' => 'https://github.com/PavelNaymovets/epam_triangle',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '10',
                                     'url' => 'https://github.com/PavelNaymovets/epam_test-sorting',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '11',
                                     'url' => 'https://github.com/PavelNaymovets/epam_test-quadratic-equation',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '12',
                                     'url' => 'https://github.com/PavelNaymovets/epam_test-factorial',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '13',
                                     'url' => 'https://github.com/PavelNaymovets/epam_streams-count-words',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '14',
                                     'url' => 'https://github.com/PavelNaymovets/epam_special-collections',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '15',
                                     'url' => 'https://github.com/PavelNaymovets/epam_quadratic-equation',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '16',
                                     'url' => 'https://github.com/PavelNaymovets/epam_electronic-watch',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '17',
                                     'url' => 'https://github.com/PavelNaymovets/epam_catch-em-all',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => '18',
                                     'url' => 'https://github.com/PavelNaymovets/epam_average',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_epam_study'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: GeekBrains */
        public function sendGeekBrainsInlineButton() {
            $textMessage = "$this->userName, Вы в разделе GeekBrains 📝\r\n\r\n<b>О курсе:</b>\r\n\r\n▫Длительность: 12 месяцев\r\n▫Место: учебная платформа GeekBrains\r\n▫Язык: русский\r\n▫Режим: удаленный\r\n▫Формат: Видеозаписи уроков. Задачи. Первый месяц был вебинарный формат, но потом его убрали. Сейчас остались только видео и номинальная проверка дз\r\n\r\nОбучение проходит по четвертям. Внутри каждой четверти есть логическое разделение на уровни, от простого к сложному, начиная с 1-го. Также на курсе есть классы предметов по выбору. Кнопки с ними имеют соответствующее название.\r\n\r\n<b>Навигация по базам данных внутри репозитория:</b>\r\n\r\n▫MySQL homework_1 – 5\r\n▫MongoDB homework_6\r\n▫PostgreSQL homework_8\r\n▫<b>Пробное задание к собеседованию</b> homework_7, файл homework_7, строка 59\r\n\r\nНиже представлены кнопки со ссылками на задачи из курса👇";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => 'Java. Уровень 1',
                                     'url' => 'https://github.com/PavelNaymovets/Java_Level1_HomeWorks_NaumovetsPR/tree/homeWork8/src/main/java/ru/gb/naumovets',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => 'Java. Уровень 2',
                                     'url' => 'https://github.com/PavelNaymovets/Java_Level2_HomeWorks_NaumovetsPR-/tree/homeWork6/src/main/java/ru/gb/naumovets',
                                     'callback_data' => 'noHandle'
                                 ),
                                 array(
                                     'text' => 'Java. Уровень 3',
                                     'url' => 'https://github.com/PavelNaymovets/Java_Level3_HomeWorks_NaumovetsPR/tree/homeWork6/src/main/java/ru/gb/naumovets',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Базы данных. SQLite',
                                     'url' => 'https://github.com/PavelNaymovets/SQLite',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => 'Базы данных. MySQL, MongoDB, PostgreSQL',
                                     'url' => 'https://github.com/PavelNaymovets/MySQL_MongoDB_PostgreSQL',
                                     'callback_data' => 'noHandle'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_geekBrains_study'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: ПРОЧЕЕ */
        public function sendOtherInlineButton() {
            $textMessage = "$this->userName, Вы в разделе прочее 💡\r\n\r\n<b>Здесь вы можете:</b>\r\n\r\n🐈 Поднять настроение. Бот пришлет случайное, забавное фото кота\r\n\r\n📊 Оценить бота. Оценка придет на почту разработчику\r\n\r\n📷 Поделиться фото. Прислать в чат с ботом забавное фото котика, которым хотите поделиться. Фото сохраниться на сервере разработчика";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => '🐈 Поднять настроение',
                                     'callback_data' => 'cheerUp'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '📊 Оценить бота',
                                     'callback_data' => 'rateBot'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '📷 Поделиться фото',
                                     'callback_data' => 'sharePhoto'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_other_mainMenu'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        /* КНОПКИ МЕНЮ. РАЗДЕЛ: ОЦЕНИТЬ БОТА */
        public function sendRateBotInlineButton() {
            $textMessage = "$this->userName, Вы в разделе оценки бота 📊\r\n\r\n📈 максимальная оценка - 5\r\n\r\n📉 минимальная оценка - 0\r\n\r\nДля оценки бота нажмите одну из кнопок ниже 👇";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => '1',
                                     'callback_data' => 'one'
                                 ),
                                 array(
                                     'text' => '2',
                                     'callback_data' => 'two'
                                 ),
                                 array(
                                     'text' => '3',
                                     'callback_data' => 'three'
                                 ),
                                 array(
                                     'text' => '4',
                                     'callback_data' => 'four'
                                 ),
                                 array(
                                     'text' => '5',
                                     'callback_data' => 'five'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '<< Назад',
                                     'callback_data' => 'back_rateBot_mainMenu'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
        //-----------------------------------------------------
        // ОБРАБОТКА НАЖАТИЯ НА КНОПКИ МЕНЮ
        //-----------------------------------------------------
        
        /* ОБРАБОТКА НАЖАТИЯ НА КНОПКИ. РАЗДЕЛ: СТАРТОВОЕ СООБЩЕНИЕ */
        public function startButtonHandler() {
            if($this->dataButton == 'pet') {
                
                $this->sendPetInlineButton();
                
            } else if($this->dataButton == 'study') {
                
                $this->sendStudyInlineButton();
                
            } else if($this->dataButton == 'other') {
                
                $this->sendOtherInlineButton();
                
            }
        }
        
        /* ОБРАБОТКА НАЖАТИЯ НА КНОПКИ. РАЗДЕЛ: PET-ПРОЕКТЫ */
        public function petButtonHandler() {
            if($this->dataButton == 'back_pet_mainMenu') {
                $this->backToStartInlineButton();
            } else if($this->dataButton == 'back_petMenu') {
                $this->sendPetInlineButton();
            } else if($this->dataButton == 'PHP_projects') {
                $this->sendPhpProjectsInlineButton();
            } else if($this->dataButton == 'Java_projects') {
                $this->sendJavaProjectsInlineButton();
            } else if($this->dataButton == 'HTML5_CSS3_projects') {
                $this->sendHtmlCssProjectsInlineButton();
            } else if($this->dataButton == 'interview_projects') {
                $this->sendInterviewTasksProjectsInlineButton();
            }
        }
        
        /* ОБРАБОТКА НАЖАТИЯ НА КНОПКИ. РАЗДЕЛ: ОБУЧЕНИЕ */
        public function studyButtonHandler() {
            if($this->dataButton == 'epam') {
                $this->sendEpamInlineButton();
            } else if($this->dataButton == 'geekBrains') {
                $this->sendGeekBrainsInlineButton();
            } else if($this->dataButton == 'back_study_mainMenu') {
                $this->backToStartInlineButton();
            } else if($this->dataButton == 'back_epam_study') {
                $this->sendStudyInlineButton();
            } else if($this->dataButton == 'back_geekBrains_study') {
                $this->sendStudyInlineButton();
            }
        }
        
        /* ОБРАБОТКА НАЖАТИЯ НА КНОПКИ. РАЗДЕЛ: ПРОЧЕЕ */
        public function otherButtonHandler() {
            if($this->dataButton == 'cheerUp') {
                
                $listFiles = $this->listFiles(__DIR__ . "/img/");
                $max = count($listFiles) - 1;
                $randFile = rand(0, $max);
                $filePath = __DIR__ . "/img/" . $listFiles[$randFile];
                $this->chat_sendOneRandomPhoto($filePath);
                
                // $this->chat_sendGroupPhoto($listFiles); //отпавка группы фотографий
                // $this->chat_sendDocument($filePath); //отправка документа в чат
                
            } else if($this->dataButton == 'rateBot') {
                
                $this->sendRateBotInlineButton();
                
            } else if($this->dataButton == 'sharePhoto') {
                
                $this->chat_sendMessage("Пришлите фото в чат😊\r\nХочу его получше рассмотреть🧐");
                
            } else if($this->dataButton == 'back_other_mainMenu') {
                
                $this->backToStartInlineButton();
                
            }
        }
        
        /* ОБРАБОТКА НАЖАТИЯ НА КНОПКИ. РАЗДЕЛ: ОЦЕНКА БОТА */
        public function rateButtonHandler() {
            if($this->dataButton == 'one') {
                $this->sendMessageToEmail(1);
                $this->chat_sendMessage('Что, совсем плохо?☹️ Буду стараться над улучшением 😊');
            } else if($this->dataButton == 'two') {
                $this->sendMessageToEmail(2);
                $this->chat_sendMessage('Не думал, что все так ужасно 😂');
            } else if($this->dataButton == 'three') {
                $this->sendMessageToEmail(3);
                $this->chat_sendMessage('Есть куда расти 😊');
            } else if($this->dataButton == 'four') {
                $this->sendMessageToEmail(4);
                $this->chat_sendMessage('Спасибо за оценку бота 😊');
            } else if($this->dataButton == 'five') {
                $this->sendMessageToEmail(5);
                $this->chat_sendMessage('Юху! Я рад, что вам понравился бот 😊');
            } else if($this->dataButton == 'back_rateBot_mainMenu') {
                $this->sendOtherInlineButton();
            }
        }
        
        /* ОБРАБОТКА НАЖАТИЯ НА КНОПКИ. КНОПКА НАЗАД: СТАРТОВОЕ МЕНЮ */
        public function backToStartInlineButton() {
            $textMessage = "Добрый день, $this->userName!\r\n\r\nС помощью этого бота Вы можете посмотреть pet-проекты и задания из курсов, которые не вошли в мое резюме😊\r\n\r\n📍Ниже приведен ряд кнопок, которые помогут с навигацией:\r\n\r\n<b>Главное меню:</b>\r\n\r\n📋 Pet-проекты. Бот предоставит ссылки на репозиторий на GitHub, где хранится код и описание pet-проектов (файл README)\r\n\r\n📝 Обучение. Бот предоставит список прочитанных мною книг, а также ссылки на репозиторий на GitHub, где хранится код и описание дз (файл README)\r\n\r\n💡 Прочее. Здесь содержатся дополнительные функции бота, которые описаны ниже 👇\r\n\r\n<b>Прочее:</b>\r\n\r\n🐈 Поднять настроение. Бот пришлет рандомную веселую картинку с котиком\r\n\r\n📊 Оценить бота. Бот пришлет шкалу, по которой можно оценить бота. Оценка придет мне на почту📩\r\n\r\n📷 Поделиться фото. Пришлите фото, документ фото (в формате png или jpg, не pdf) котика в чат. Бот сохранит его на сервер. Возможно именно Ваше фото поднимет настроение следующему пользователю😊\r\n\r\n<b>Бот понимает команды:</b>\r\n\r\n🔸 /start\r\n🔸 /hello\r\n🔸 /bye\r\n\r\nСпасибо, что воспользовались моим ботом и узнали дополнительную информацию обо мне!\r\n\r\nНадеюсь, информация была вам полезна👍";
    
            $arrayQuery = array(
                 'chat_id' => $this->chatId,
                 'message_id' => $this->messageId,
                 'text' => $textMessage,
                 'parse_mode' => 'html',
                 'reply_markup' => json_encode(
                     array(
                         'inline_keyboard' => array(
                             array(
                                 array(
                                     'text' => '📋 Pet-проекты',
                                     'callback_data' => 'pet'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '📝 Обучение',
                                     'callback_data' => 'study'
                                 )
                             ),
                             array(
                                 array(
                                     'text' => '💡 Прочее',
                                     'callback_data' => 'other'
                                 )
                             )
                         )
                     )
                 )
            );
            $this->chat_editMessage($arrayQuery);
        }
        
    }

?>