<?
class sendMails extends waCliController {

    public function execute() {
		
	// подключаем класс
		require_once( __DIR__.'/workWithBasket.php' ); 
		
		
	// создаем объект обработки
		$PROCESS = new workWithBasket(); 
		

	// Если параметр не задан из консоли (количество отправляемых писем за итерацию), то задаем явно
		if (empty($argv[1]))
			$giveData = array(
				'mailsLimit' => 100, 
				'stepID' => $PROCESS->wwb_loadStep(), 
				'environment' => 'BROWSER'
			);
		else 
			$giveData = array(
				'mailsLimit' => $argv[1], 
				'stepID' => $PROCESS->wwb_loadStep(), 
				'environment' => 'CRON'
			);
		
		
	// Задаем текущую дату для расчета
		if (!$PROCESS->aDate = date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), time()))
			$PROCESS->aDate = date("d.m.Y h:i:s"); // На всякий случай - вдруг средствами Bitrix не получится
		
		
	//устанавливаем параметры и собираем пользователей в arUsers
		$PROCESS->wwb_setParameters($giveData);
		
		if ($arUsers = $PROCESS->wwb_getUsers($giveData)) {
		
		
		// Если есть пользователи (Обход не закончен на предыдущем шаге) - обходим польователей, и шлем им письма
			$PROCESS->wwb_beginSending($arUsers);

		} else {
			
			
		// Если лист пользователей пуст
			$PROCESS->wwb_add2Log("Скрипт завершил работу на USER_ID = " . $PROCESS->wwb_loadStep() );
			$PROCESS->wwb_resetStep();
			
		}
		
		$PROCESS->wwb_saveStep($user['ID']);
		
    }
}
?>