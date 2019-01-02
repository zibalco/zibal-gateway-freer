<?
/*
  Virtual Freer
	zibal plugin
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License v3 (http://www.gnu.org/licenses/gpl-3.0.html)
  as published by the Free Software Foundation.
*/
	//-- اطلاعات کلی پلاگین
	$pluginData[zibalzb n][type] = 'payment';
	$pluginData[zibal][name] = 'درگاه زیبال';
	$pluginData[zibal][uniq] = 'zibal';
	$pluginData[zibal][description] = 'مخصوص پرداخت با دروازه پرداخت <a href="http://zibal.ir">زیبال</a>';
	$pluginData[zibal][author][name] = 'zibal team';
	$pluginData[zibal][author][url] = 'https://zibal.ir';
	$pluginData[zibal][author][email] = 'zamanzadeh@zibal.ir';
	
	//-- فیلدهای تنظیمات پلاگین
	$pluginData[zibal][field][config][1][title] = 'کد مرچنت';
	$pluginData[zibal][field][config][1][name] = 'merchant';
	$pluginData[zibal][field][config][2][title] = 'عنوان خرید';
	$pluginData[zibal][field][config][2][name] = 'title';
	
	//-- تابع انتقال به دروازه پرداخت
	function gateway__zibal($data)
	{
		global $config,$db,$smarty;
		include_once('include/libs/nusoap.php');
		$merchantID 	= trim($data[merchant]);
		$amount 		= round($data[amount]/10);
		$invoice_id		= $data[invoice_id];
		$callBackUrl 	= $data[callback];
		
		$parameters = array(
    "merchant"=> $merchantID,//required
    "callbackUrl"=> $callBackUrl,//required
    "amount"=> $data[amount],//required
    "orderId"=> $data[invoice_id],//optional
    "mobile"=> $data[mobile],//optional
"description"=> $data[title]
);
$res = postToZibal('request', $parameters);
	
		if ($res->result == 100)
		{
			$update[payment_rand]		= $res->trackId;
			$sql = $db->queryUpdate('payment', $update, 'WHERE `payment_rand` = "'.$invoice_id.'" LIMIT 1;');
			$db->execute($sql);
			header('location: https://gateway.zibal.ir/start/' . $res->trackId.'/direct');
			exit;
		}
		else
		{
			$data[title] = 'خطای سیستم';
			$data[message] = '<font color="red">در اتصال به درگاه زیبال مشکلی به وجود آمد٬ لطفا از درگاه سایر بانک‌ها استفاده نمایید.</font>'.$res['Status'].'<br /><a href="index.php" class="button">بازگشت</a>';
			$query	= 'SELECT * FROM `config` WHERE `config_id` = "1" LIMIT 1';
			$conf	= $db->fetch($query);
			$smarty->assign('config', $conf);
			$smarty->assign('data', $data);
			$smarty->display('message.tpl');
		}
	}
	
	//-- تابع بررسی وضعیت پرداخت
	function callback__zibal($data)
	{
		global $db,$get;
		$trackId 	= $_POST['trackId'];
		$ref_id = $_POST['refNumber'];
		if ($_POST['success'] == '1')
		{
			$res = postToZibal('request', $parameters);
			$merchantID = $data[merchant];
			$sql 		= 'SELECT * FROM `payment` WHERE `payment_rand` = "'.$trackId.'" LIMIT 1;';
			$payment 	= $db->fetch($sql);

			$res = postToZibal('verify', array(
					'merchant'	 => $merchantID,
					'trackId' 	 => $trackId
				)
			);
			if ($payment[payment_status] == 1)
			{
				if ($res->result == 100)//-- موفقیت آمیز
				{
					//-- آماده کردن خروجی
					$output[status]		= 1;
					$output[res_num]	= $trackId;
					$output[ref_num]	= $res->refNumber;
					$output[payment_id]	= $payment[payment_id];
				}
				else
				{
					//-- در تایید پرداخت مشکلی به‌وجود آمده است‌
					$output[status]	= 0;
					$output[message]= 'پرداخت توسط زیبال تایید نشد‌.'.$res['Status'];
				}
			}
			else
			{
				//-- قبلا پرداخت شده است‌
				$output[status]	= 0;
				$output[message]= 'سفارش قبلا پرداخت شده است.';
			}
		}
		else
		{
			//-- شماره یکتا اشتباه است
			$output[status]	= 0;
			$output[message]= 'شماره یکتا اشتباه است.';
		}
		return $output;
	}

/**
 * connects to zibal's rest api
 * @param $path
 * @param $parameters
 * @return stdClass
 */
function postToZibal($path, $parameters)
{
    $url = 'https://gateway.zibal.ir/'.$path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response  = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}
