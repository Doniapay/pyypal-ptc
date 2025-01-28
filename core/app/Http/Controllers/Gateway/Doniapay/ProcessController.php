<?php

namespace App\Http\Controllers\Gateway\Doniapay;

use App\Models\Deposit;
use App\Models\GeneralSetting;
use App\Http\Controllers\Gateway\PaymentController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProcessController extends Controller
{

    /*
     * Doniapay Gateway
     */
    public static function process($deposit)
    { 
        $donia = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        $invoice_id = $deposit->trx;
        $data   = array(
            "cus_name"          => $deposit->user->username,
            "cus_email"         => $deposit->user->email,
            "amount"            => round($deposit->final_amo,2),
            "success_url"       => route('ipn.'.$deposit->gateway->alias)."?inv=" . $invoice_id,
            "cancel_url"        => route(gatewayRedirectUrl()),
        );

        $header   = array(
            "api"               => $donia->api_key,
            "secret"            => $donia->secret_key,
            "position"          => $donia->hostname,
            "url"               => "https://pay.doniapay.com/request/payment/payment_url",
        );

        $res = self::payments($data,$header);

        $res = json_decode($res, true);


        if ($res['status']=="success") {
            $send['redirect'] = true;
            $send['redirect_url'] = $res['payment_url'];
        } else {
            $send['error'] = true;
            $send['message'] = "Credentials mismatch. Please contact with admin";
        }
        return json_encode($send);
    }

    public function ipn()
    {
        
        $track = $_GET['inv'];
        $deposit = Deposit::where('trx', $track)->orderBy('id', 'DESC')->first();

        $donia = json_decode($deposit->gatewayCurrency()->gateway_parameter);
        
        $header   = array(
            "api"               => $donia->api_key,
            "secret"            => $donia->secret_key,
            "position"          => $donia->hostname,
            "url"               => "https://pay.doniapay.com/request/payment/verify",
        );
        $trxId = $_GET['transactionId'];

        $data   = array(
            "transaction_id" 		=> $trxId,
        );

        $response = self::payments($data,$header);

		$data = json_decode($response, true);


		if ($data['status']==1) {        
            PaymentController::userDataUpdate($deposit->trx);
            $notify[] = ['success', 'Transaction was successful.'];
            return redirect()->route(gatewayRedirectUrl(true))->withNotify($notify);
        }

        session()->forget('deposit_id');
        session()->forget('payment_id');

        $notify[] = ['error', 'Invalid request.'];
        return redirect()->route(gatewayRedirectUrl())->withNotify($notify);
        
    }
    
    public static function payments($data = "",$header='') {
        
        $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'app-key: ' . $header['api'],
            'secret-key: ' . $header['secret'],
            'host-name: ' . $header['position'],
        );
        $url = $header['url'];
        $curl = curl_init();
        $data = http_build_query($data);
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_VERBOSE =>true
        ));
         
        $response = curl_exec($curl);
        curl_close($curl);
        
        return $response;
    }
}
