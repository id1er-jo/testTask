<?
/*** Класс для работы с корзиной 	***/
/*** Разработка id1er.jo 			***/

class workWithBasket {

	var $mailsLimit, 	// Лимит сообщений
		$environment, 	// Окружение
		$stepID, 		// Последний обработанный ID
		$aDate, 		// Такущая дата
		$arBasketItems, // Массив товаров в корзине
		$arOrderItems,	// Массив товаров в заказах
		$arUsers;		// Массив пользователей

		
/*** Метод установки параметров рассылки ***/
	function wwb_setParameters($input) {
		$this->mailsLimit = $input['mailsLimit'];
		$this->environment = $input['environment'];
		$this->stepID = $input['stepID'];
	}
	
	
/*** Метод для получения пользователей, начиная ОТ step, в количестве countElementsForIterration ***/
	function wwb_getUsers($input) {
		$stepID = $this->stepID;
		$order = array('sort' => 'asc');
		$tmp = 'sort';		
		$arFilter = Array(">ID" => $stepID);
		
		$rsUsers = CUser::GetList($order, $tmp, $arFilter, $arParams);
		while ($arUser = $rsUsers->Fetch()) {
			$this->arUsers[] = $arUser;
		}
		
		return $this->arUsers;
	}


/*** Метод для получения товаров из заказов пользователя по его ID за предыдущий месяц ***/
	function wwb_getOrderItems($userid) {
		$arFilter = Array(
		   "USER_ID" => $userid,
		   ">=DATE_INSERT" => $this->aDate
		   );

		$db_sales = CSaleOrder::GetList(array("DATE_INSERT" => "ASC"), $arFilter);
		
		while ($ar_sales = $db_sales->Fetch()) {
			$dbBasketItems = CSaleBasket::GetList(array(), array("ORDER_ID" => $ar_sales['ID']), false, false, array('PRODUCT_ID'));
			while ($arItem = $dbBasketItems->Fetch()) {
				$itemsArray[] = $arItem['PRODUCT_ID'];
			}
		}
		$this->arOrderItems = $itemsArray;
		
		return $this->arOrderItems;
	}

	
/*** Метод для получения товаров из корзины пользователя по его ID ***/
	function wwb_getBasketItems($userid) {
	
		$this->arBasketItems = array();
	
		$arOrderItems = $this->wwb_getOrderItems($userid); 

		$basketUserId = Bitrix\Sale\Fuser::getIdByUserId($userid);
		$basket = Bitrix\Sale\Basket::loadItemsForFUser($basketUserId, Bitrix\Main\Context::getCurrent()->getSite()); 

		foreach ($basket as $basketItem) {
			if (
				$basketItem->isDelay() && 											// Отложен ли товар
				$basketItem->canBuy() && 											// Доступен ли для покупки
				$this->wwb_isItMonthAgo($basketItem->getField('DATE_INSERT')) && 	// Проверяются все отложенные за ближайший месяц 
				!in_array($basketItem->getField('PRODUCT_ID'), $arOrderItems)		// Есть ли ID товаров в заказах
			)
				$this->arBasketItems['ITEMS'][] = $basketItem->getField('NAME') . ' - ' . $basketItem->getQuantity() . ' шт.';
		}

//		$this->arBasketItems['TOTAL_PRICE'] = $basket->getPrice(); Можно цену получить, отключено за ненадоностью
		
		return $this->arBasketItems;
	}
	
	
/*** Отладочный метод для вывода ***/
	function wwb_printData($input) {
		echo '<pre>';
			print_r($input);
		echo '</pre>';	
	}

	
/*** Метод для сравнения дат ***/
	function wwb_isItMonthAgo($aDate) {
		$d = new DateTime($aDate);
		$d->modify("-1 month");
		if ($this->aDate < $aDate) return true;
		return false;
	}


/*** Метод для подготовки массива к отправке ***/
	function wwb_prepareArrayToSend($mailData) {		
		$mailData['TO'] = 'MMV.94@inbox.ru'; // Слал для проверки на свой ящик. Для рассылки закоментировать/удалить
		$mailData['SUBJECT'] = "Заголовок письма";
		
		$mailData['MESSAGE'] = "<html><head>
			<link rel='stylesheet' type='text/css'>
			<base target='_blank'>
			</head><body status='show'>";
		$mailData['MESSAGE'] .= "Добрый день, ".$mailData['NAME']." ". $mailData['LAST_NAME']."! В вашем вишлисте хранятся товары: <br>";
		foreach ($mailData['ITEMS']['ITEMS'] as $key => $item) {
			$key++;
			$mailData['MESSAGE'] .= $key . ". " .$item."<br>";
		}		
		$mailData['MESSAGE'] .= "</body></html>";

		$mailData['HEADERS'] =  'From: test@'. $_SERVER['HTTP_HOST'] . "\r\n" .
			'Reply-To: test@'. $_SERVER['HTTP_HOST'] . "\r\n" .
			 "Content-Type: text/html; charset=UTF-8\n" .
			'X-Mailer: PHP/' . phpversion();
			
		return $mailData;
	}

	
/*** Метод для записи в лог ***/
	function wwb_add2Log($sendMail) {
		$log = fopen( __DIR__."/sendingLog.txt", 'a') or $log = fopen( __DIR__."/sendingLog.txt", 'w'); 
		$str = $sendMail . "\r\n";
		fwrite($log, $str);
		fclose($log);
	}

	
/*** Методы для пошагового выполнения ***/
	function wwb_loadStep() {
		$step = file( __DIR__."/step.txt");
		$step[0]++;
		return $step[0];
	}

	function wwb_saveStep($step) {
		$fp = fopen( __DIR__.'/step.txt', 'w+');
		fwrite($fp, $step);
		fclose($fp);
	}

	function wwb_resetStep() {
		$fp = fopen( __DIR__.'/step.txt', 'w+');
		fclose($fp);
	}


/*** Метод для отправки письма штатным PHP ***/
	function wwb_sendMail($mailData) {
		$to = $mailData['TO'];
		$subject = $mailData['SUBJECT'];
		$message = $mailData['MESSAGE'];
		$headers = $mailData['HEADERS'];
		if( mail($to,$subject,$message,$headers) ){
			return "Письмо отправлено: $to";
		}else{
			return "Ошибка отправки: $to";
		}
	}


/*** Метод для рассылки штатным PHP ***/
	function wwb_beginSending($arUsers) {
		$mailsLimit = $this->mailsLimit;
		$i = 0;	
		
		foreach ($arUsers as $user) {
			$arBasketItems = $this->wwb_getBasketItems($user['ID']);
			
			if ($arBasketItems) {
				
				$loadArray = array(
					'USER_ID' => $user['ID'],
					'TO' => $user['EMAIL'],
					'NAME' => $user['NAME'],
					'LAST_NAME' => $user['LAST_NAME'],
					'ITEMS' => $arBasketItems
				);
			
				$prepareArrayToSend = $this->wwb_prepareArrayToSend($loadArray);
				//$this->wwb_printData($prepareArrayToSend['MESSAGE']);
				
				$sendMail = $this->wwb_sendMail($prepareArrayToSend); 	
				$this->wwb_add2Log($sendMail);
				
				if ($i > $mailsLimit) {
		
					$this->wwb_add2Log("Скрипт завершил работу на USER_ID = " . $user['ID'] );
					$this->wwb_resetStep();	
					break;
					
				}

				$i++;
				
			} else {
				$this->wwb_add2Log("Отложенных товаров для " . $user['EMAIL'] . " нет.");
			}
		}
				
		$this->wwb_add2Log("Выполнение завершено. Писем отправлено: " . $i . "; текущий USER_ID = " . $user['ID'] . '.');
		
	}

	
/*** Метод для отправки письма через почтовое событие ***/
/*** ВНИМАНИЕ, КОД НЕ ТЕСТИРОВАЛСЯ: ***/

/***
	function wwb_sendMail($mailData) {
		$sendMail = Bitrix\Main\Mail\Event\Event::send(array(
			"EVENT_NAME" => "SEND_ARRAY_OF_DELAYED_PRODUCTS",
			"LID" => "s1",
			"C_FIELDS" => array(
				"EMAIL" => $mailData['TO'],
				"USER_ID" => $mailData['USER_ID']
			),
		)); 
		return $sendMail;
	}
***/
	
	
}

?>
