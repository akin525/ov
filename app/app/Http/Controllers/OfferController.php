<?php

namespace App\Http\Controllers;

use App\CommissionLog;
use App\Cryptoescrow;
use App\Paymentmethod;
use App\GeneralSetting;
use App\Currency;
use App\Lib\GoogleAuthenticator;
use App\Cryptowallet;
use App\SupportAttachment;
use App\SupportMessage;
use App\SupportTicket;
use App\Curr;
use App\Trx;
use App\User;
use App\UserWallet;
use App\Cryptowithdraw;
use App\Cryptoffer;
use App\Cryptotrade;
use App\Cryptotradechat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Image;
use File;
use Validator;
use Session;


class OfferController extends Controller
{

   public function create()
    {

        $data['crypto'] = Currency::where('status', 1)->whereCanoffer(1)->orderBy('name','asc')->get();
        $data['page_title'] = "Create Offer";

        return view('user.offers.create', $data);
    }

   public function createoffer2($id)
    {

        $data['crypto'] = Currency::where('status', 1)->whereSymbol($id)->whereCanoffer(1)->orderBy('name','asc')->first();
        $data['curr'] = Curr::where('status', 1)->orderBy('name','asc')->get();
        $data['pmethod'] = Paymentmethod::where('status', 1)->orderBy('name','asc')->get();
        if(!$data['crypto']){
        $notify[] = ['error', 'Invalid cryptocurrency'];
        return back()->withNotify($notify)->withInput();
        }
        $data['wallet'] = Cryptowallet::where('status', 1)->whereUser_id(Auth::id())->whereCoin_id($data['crypto']->id)->orderBy('id','asc')->get();
        $countwallet = Cryptowallet::where('status', 1)->whereUser_id(Auth::id())->whereCoin_id($data['crypto']->id)->orderBy('id','asc')->count();
        if($countwallet < 1){
        $notify[] = ['error', 'You currenctly dont have any ' .$data['crypto']->name.' wallet. Please create a wallet and try again later '];
        return back()->withNotify($notify)->withInput();
        }
        $data['page_title'] = "Create New Offer";

        return view('user.offers.create-step2', $data);
    }


   public function postoffer(Request $request, $id)
    {
         $this->validate($request, [
            'pmethod' => 'required',
            'account' => 'required',
            'note' => 'required',
            'min' => 'required',
            'currency' => 'required',
            'max' => 'required',
            'expire' => 'required',
            'rate' => 'required'
        ]);


        $wallet = Cryptowallet::where('status', 1)->whereUser_id(Auth::id())->whereId($request->wallet)->orderBy('id','asc')->first();
        $countwallet = Cryptowallet::where('status', 1)->whereUser_id(Auth::id())->whereId($request->wallet)->orderBy('id','asc')->count();
        $country = Curr::where('status', 1)->whereId($request->currency)->first();
          if(!$country){
        $notify[] = ['error', 'Invalid Country/Currency or address not found'];
        return back()->withNotify($notify)->withInput();
        }
         if($request->min >= $request->max){
        $notify[] = ['error', 'Invakid range. Your maximum amount must be greater than minimum'];
        return back()->withNotify($notify)->withInput();
        }

        if($countwallet < 1){
        $notify[] = ['error', 'Invalid wallet address or address not found'];
        return back()->withNotify($notify)->withInput();
        }

        $crypto = Currency::where('status', 1)->whereId($id)->orderBy('name','asc')->first();
        if(!$crypto){
        $notify[] = ['error', 'Invalid cryptocurrency or cryptocurrency  not found'];
        return back()->withNotify($notify)->withInput();
        }

       if($request->max > $wallet->balance){
        $notify[] = ['error', 'Insufficient fund in your wallet balance'];
        return back()->withNotify($notify)->withInput();
       }

       if(Auth::user()->kyc < 1){
        $notify[] = ['error', 'You  need to verifiy your account before creating an offer'];
        return back()->withNotify($notify)->withInput();
       }

       $w['coin_id'] = $crypto->id; // wallet method ID
            $w['user_id'] = Auth::id();
            $w['wallet_id'] = $wallet->id; // User Wallet ID
            $w['code'] = getTrx();
            $w['min'] = $request->min;
            $w['expire'] = $request->expire;
            $w['max'] = $request->max;
            $w['rate'] = $request->rate;
            $w['country'] = $country->country;
            $w['currency'] = $country->name;
            $w['payment_method'] = $request->pmethod;
            $w['account'] = $request->account;
            $w['note'] = $request->note;

            $result = Cryptoffer::create($w);
            if($result){
            $notify[] = ['success', 'Your new crypto offer has been created'];
            return redirect()->route('user.myoffers')->withNotify($notify);
            }
            else{
             $notify[] = ['error', 'Sorry we cant create your offer at themoent , please contact admin'];
             return back()->withNotify($notify)->withInput();
            }
    }

      public function myoffers()
    {

        $data['offers'] = Cryptoffer::whereUser_id(Auth::id())->orderBy('id','desc')->paginate(10);
        $data['page_title'] = "My Offers";

        return view('user.offers.myoffers', $data);
    }

  public function deleteoffer($id)
    {

        $offer = Cryptoffer::whereUser_id(Auth::id())->whereCode($id)->first();
         if(!$offer){
        $notify[] = ['error', 'We cant find your market offer'];
        return back()->withNotify($notify)->withInput();
            }
         $offer->delete();
         $notify[] = ['success', 'Market Offer Deleted Successfully'];
        return back()->withNotify($notify)->withInput();
    }
    public function disableoffer($id)
    {

        $offer = Cryptoffer::whereUser_id(Auth::id())->whereCode($id)->first();
         if(!$offer){
        $notify[] = ['error', 'We cant find your market offer'];
        return back()->withNotify($notify)->withInput();
            }
        $offer->status = 0;
        $offer->save();
         $notify[] = ['success', 'Market Offer Deactivated Successfully'];
        return back()->withNotify($notify)->withInput();
    }

    public function activateoffer($id)
    {

        $offer = Cryptoffer::whereUser_id(Auth::id())->whereCode($id)->first();
         if(!$offer){
        $notify[] = ['error', 'We cant find your market offer'];
        return back()->withNotify($notify)->withInput();
            }
        $offer->status = 1;
        $offer->save();
         $notify[] = ['success', 'Market Offer Activated Successfully'];
        return back()->withNotify($notify)->withInput();
    }
     public function manageoffer($id)
    {

        $data['offer'] = Cryptoffer::whereUser_id(Auth::id())->whereCode($id)->first();
         if(!$data['offer']){
        $notify[] = ['error', 'We cant find your market offer'];
        return back()->withNotify($notify)->withInput();
        }
        $data['ptrade'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->where('status', 0 || 'dispute', 1)->get();
        $data['strade'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->whereStatus(1)->whereDispute(0)->wherePaid(1)->get();
        $data['successful'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->whereStatus(1)->whereDispute(0)->wherePaid(1)->sum('amount');
        $data['pending'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->orderby('id','desc')->where('status', 0 || 'dispute', 1)->sum('amount');
        $data['declined'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->whereDispute(1)->sum('amount');
        $data['suc'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->whereStatus(1)->whereDispute(0)->wherePaid(1)->count();
        $data['pend'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->whereStatus(0)->count();
        $data['dec'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->whereDispute(1)->count();
        $data['count'] = Cryptotrade::whereUser_id(Auth::id())->whereMarketcode($id)->count();
        $data['page_title'] = "Manage Offer";
        return view('user.offers.trades', $data);


    }

   public function p2p()
    {

        $data['crypto'] = Currency::where('status', 1)->whereCanoffer(1)->orderBy('name','asc')->get();
        $data['country'] = Curr::where('status', 1)->orderBy('name','asc')->get();
        $data['page_title'] = "Search Offer";

        return view('user.offers.search', $data);
    }

 public function searchp2p(Request $request)
    {
         $this->validate($request, [
            'country' => 'required',
            'currency' => 'required',
        ]);


        $count = Cryptoffer::whereCoin_id($request->currency)->where('status', 1)->orderBy('id','desc')->count();
       if($count < 1){
        $notify[] = ['error', 'We cant find any market offer for the selected cryptocurrency'];
        return back()->withNotify($notify)->withInput();
       }
        $data['offer'] = Cryptoffer::whereCoin_id($request->currency)->where('status', 1)->orderBy('id','desc')->get();
        $crypto = Currency::whereId($request->currency)->where('status', 1)->orderBy('id','desc')->first();
        if(!$crypto){
        $notify[] = ['error', 'cryptocurrency not found'];
        return back()->withNotify($notify)->withInput();
       }
        $data['page_title'] = "Search Offer";

        Session::put('country', $request->country);
        Session::put('coin', $request->currency);
        if($request->country == 'allcount'){
        $notify[] = ['success', 'Available Market Offers'];
        return redirect()->route('user.market')->withNotify($notify);
        }
        else{

        $data['offer'] = Cryptoffer::whereCoin_id($request->currency)->where('status', 1)->whereCountry($request->country)->orderBy('id','desc')->get();
        $count = Cryptoffer::whereCoin_id($request->currency)->where('status', 1)->whereCountry($request->country)->orderBy('id','desc')->count();
        if($count < 1){
        $notify[] = ['error', 'We cant find any '.$crypto->name.' market offer in '.$request->country.' at the moment'];
        return back()->withNotify($notify)->withInput();
        }
         $notify[] = ['success', 'Available Market Offers'];
        return redirect()->route('user.market')->withNotify($notify);

        }

    }

       public function market()
    {
        $country = Session::get('country');
        $coin = Session::get('coin');
        if($country == 'allcount'){
        $data['offer'] = Cryptoffer::whereCoin_id($coin)->where('status', 1)->orderBy('id','desc')->paginate(10);
        $data['coin'] = Currency::where('status', 1)->whereId($coin)->orderBy('name','asc')->first();
        }
        else{
        $data['offer'] = Cryptoffer::whereCoin_id($coin)->where('status', 1)->whereCountry($country)->orderBy('id','desc')->paginate(10);
        $data['coin'] = Currency::where('status', 1)->whereId($coin)->orderBy('name','asc')->first();
        }
        $data['page_title'] = "Market Offer";

        return view('user.offers.market', $data);
    }


     public function viewoffer($id)
    {

        $data['offer'] = Cryptoffer::whereCode($id)->where('status', 1)->orderBy('id','desc')->first();
         if(! $data['offer']){
        $notify[] = ['error', 'Invalid market offer'];
        return back()->withNotify($notify)->withInput();
        }

        $data['wallet'] = Cryptowallet::where('status', 1)->whereUser_id(Auth::id())->whereCoin_id($data['offer']->coin_id)->orderBy('id','asc')->get();
        $countwallet = Cryptowallet::where('status', 1)->whereUser_id(Auth::id())->whereCoin_id($data['offer']->coin_id)->orderBy('id','asc')->count();
        $data['coin'] = Currency::where('status', 1)->whereId($data['offer']->coin_id)->orderBy('name','asc')->first();
        if($countwallet < 1){
        $notify[] = ['error', 'You dont have any '.$data['coin']->name.' wallet address yet. PLease create one first'];
        return back()->withNotify($notify)->withInput();
        }
        $data['page_title'] = "View Market Offer";

        return view('user.offers.view-offer', $data);
    }

       public function contactseller(Request $request, $id)
    {
          $this->validate($request, [
            'amount' => 'required|integer',
            'wallet' => 'required'
        ]);

        $data['offer'] = Cryptoffer::whereCode($id)->where('status', 1)->orderBy('id','desc')->first();
         if(! $data['offer']){
        $notify[] = ['error', 'Invalid market offer'];
        return back()->withNotify($notify)->withInput();
        }


        $wallet = Cryptowallet::whereUser_id($data['offer']->user_id)->whereId($data['offer']->wallet_id)->where('status', 1)->orderBy('id','desc')->first();
         if(! $wallet){
        $notify[] = ['error', 'Invalid seller wallet account'];
        return back()->withNotify($notify)->withInput();
        }

           if($request->amount > $data['offer']->max){
             $notify[] = ['error', 'You cant buy more than the market cap of $'.$data['offer'] ->min];
             return back()->withNotify($notify)->withInput();
            }

            if($request->amount < $data['offer']->min){
             $notify[] = ['error', 'You cant buy below the set market cap of $'.$data['offer'] ->min];
             return back()->withNotify($notify)->withInput();
            }



             $now = Carbon::now();
            $code = getTrx();


          $coin = Currency::where('status', 1)->whereId($data['offer']->coin_id)->orderBy('name','asc')->first();

           if(!$coin){
           $notify[] = ['error', 'Currency is currently disabled'];
           return back()->withNotify($notify)->withInput();
            }

         // $baseurl = "https://coinremitter.com/api/v3/".$coin->symbol."/get-fiat-to-crypto-rate";
		  $baseurl = "https://min-api.cryptocompare.com/data/price?fsym=BTC&tsyms=USD";
		  $curl = curl_init();
		  curl_setopt_array($curl, array(
		  CURLOPT_URL => $baseurl,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  //CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_CUSTOMREQUEST => 'GET',
		  CURLOPT_POSTFIELDS => array('api_key' => $coin->apikey,'password' => $coin->apipass,'fiat_amount' => 1,'fiat_symbol' => 'USD'),
		));

		$response = curl_exec($curl);
		$reply = json_decode($response,true);
		curl_close($curl);
		//return $response;

         if (!isset($reply['USD'])){
         $rate = 1;
         }
         else
		 {
		 $rate = $reply['USD'];
		 }
         $unit = $request->amount/$rate;

         $fee =  $coin->fee/$unit * 100;
         $total = $fee + $unit;


         if($total > $wallet->balance){
              $notify[] = ['error', 'Seller does not have sufficient '.$coin->name.' in reserve wallet'];
              return back()->withNotify($notify)->withInput();
            }


            $w['user_id'] = $data['offer']->user_id;
            $w['coin'] = $coin->id;
            $w['amount'] = $request->amount;
            $w['marketcode'] = $data['offer']->code;
            $w['trx'] = $code;
            $w['status'] = 0;
            $w['wallet'] = $request->wallet;
            $w['units'] = $unit;
            $w['buyer'] = Auth::id();
            $w['paid'] = 0;
            $w['expire'] = $now->addMinutes($data['offer']->expire);
            $result = Cryptotrade::create($w);

            $t['receiver'] = $data['offer']->user_id;
            $t['message'] = "Hi, i want to buy ".$coin->name." worth of ".$request->amount." USD";
            $t['marketcode'] = $data['offer']->code;
            $t['tradecode'] = $code;
            $t['type'] = 1;
            $t['sender'] = Auth::id();
            $cht = Cryptotradechat::create($t);



            $esc['user_id'] = $data['offer']->user_id;
            $esc['coin_id'] = $coin->id;;
            $esc['trade_code'] = $code;
            $esc['amount'] = $unit;
            $esc['fee'] = $fee;
            $esc['wallet_id'] = $wallet->id;
            $esc['status'] = 0;
            $scrow = Cryptoescrow::create($esc);

            $wallet->balance = $total;
            $wallet->save();


            Session::put('trx',  $w['trx']);
            if($result){
            $data['page_title'] = "Contact Seller";
            $notify[] = ['success', 'Your new crypto offer has been created and coin has been held safely in escrow'];
            return redirect()->route('user.chatbuyer')->withNotify($notify);
            }
            else{
             $notify[] = ['error', 'Sorry we cant patch you with the sellet at the moment , please contact admin'];
             return back()->withNotify($notify)->withInput();
            }

    }

      public function chatbuyer()
    {
        $trx = Session::get('trx');
        $data['trade'] = Cryptotrade::whereBuyer(Auth::id())->where('trx', $trx)->orderBy('id','desc')->first();
        $data['offer'] = Cryptoffer::whereCode($data['trade']->marketcode)->orderBy('id','desc')->first();
        $data['coin'] = Currency::whereId($data['offer']->coin_id)->orderBy('id','desc')->first();
        $data['chat'] = Cryptotradechat::whereTradecode($data['trade']->trx)->orderBy('id','desc')->get();
        if(! $data['offer']){
        $notify[] = ['error', 'Invalid market offer'];
        return back()->withNotify($notify)->withInput();
        }
        $data['now'] = Carbon::now();
        $data['page_title'] = "Chet Seller";

        return view('user.offers.chatbuyer', $data);
    }

      public function replychatbuyer(Request $request)
    {
         $this->validate($request, [
            'message' => 'required|string'
        ]);

        $data['trade'] = Cryptotrade::whereBuyer(Auth::id())->where('id', $request->id)->orderBy('id','desc')->first();
        if(! $data['trade']){
        $notify[] = ['error', 'Invalid market trade'];
        return back()->withNotify($notify)->withInput();
        }
        $now = Carbon::now();
        if( $data['trade']->expire < $now){
         $notify[] = ['error', 'Payment timer has elapsed. Trade has been closed by psystem. You can no longer continue this trade'];
        return back()->withNotify($notify)->withInput();
        }

            $t['receiver'] = $data['trade']->user_id;
            $t['message'] = $request->message;
            $t['marketcode'] = $data['trade']->marketcode;
            $t['tradecode'] = $data['trade']->trx;
            $t['type'] = 1;
            $t['sender'] = Auth::id();
            $cht = Cryptotradechat::create($t);
            return back();


    }

       public function chatseller($id)
    {
        $data['trade'] = Cryptotrade::whereBuyer(Auth::id())->where('trx', $id)->orderBy('id','desc')->first();
        $data['offer'] = Cryptoffer::whereCode($data['trade']->marketcode)->orderBy('id','desc')->first();
        $data['coin'] = Currency::whereId($data['offer']->coin_id)->orderBy('id','desc')->first();
        $data['chat'] = Cryptotradechat::whereTradecode($id)->orderBy('id','desc')->get();
        if(! $data['offer']){
        $notify[] = ['error', 'Invalid market offer'];
        return back()->withNotify($notify)->withInput();
        }

        $data['page_title'] = "Chet Seller";
        $data['now'] = Carbon::now();

        return view('user.offers.chatseller', $data);
    }

       public function replychatseller(Request $request)
    {
         $this->validate($request, [
            'message' => 'required|string'
        ]);

        $data['trade'] = Cryptotrade::whereUser_id(Auth::id())->where('id', $request->id)->orderBy('id','desc')->first();

        if(! $data['trade']){
        $notify[] = ['error', 'Invalid market trade'];
        return back()->withNotify($notify)->withInput();
        }
        $now = Carbon::now();
        if( $data['trade']->expire < $now){
         $notify[] = ['error', 'Trade closed by payment window timer. You can no longer continue this trade'];
        return back()->withNotify($notify)->withInput();
        }

            $t['receiver'] = Auth::id();
            $t['message'] = $request->message;
            $t['marketcode'] = $data['trade']->marketcode;
            $t['tradecode'] = $data['trade']->trx;
            $t['type'] = 2;
            $t['sender'] = $data['trade']->user_id;
            $cht = Cryptotradechat::create($t);
            return back();


    }

        public function tradepaid($id)
    {


        $trade = Cryptotrade::whereBuyer(Auth::id())->where('trx', $id)->orderBy('id','desc')->first();

        if(! $trade){
        $notify[] = ['error', 'Invalid market trade'];
        return back()->withNotify($notify)->withInput();
        }

         $now = Carbon::now();
        if( $trade->expire < $now){
         $notify[] = ['error', 'Trade closed by payment window timer. You can no longer continue this trade'];
        return back()->withNotify($notify)->withInput();
        }

        $trade->paid = 1;
        $trade->save();
        $notify[] = ['success', 'You have declared payment for this trade.Please wait whhile seller confimrs your payment. You can click on dispute button fromyour trade log to log a dispute on this trade ifyou see a foul play'];
        return back()->withNotify($notify)->withInput();

    }

         public function tradeapprove($id)
    {


        $trade = Cryptotrade::whereUser_id(Auth::id())->where('trx', $id)->orderBy('id','desc')->first();
        $escrow = Cryptoescrow::whereUser_id($trade->user_id)->whereTrade_code($trade->trx)->where('status', 0)->orderBy('id','desc')->first();
        $wallet = Cryptowallet::whereUser_id($trade->buyer)->whereId($trade->wallet)->orderBy('id','desc')->first();

        if(! $trade){
        $notify[] = ['error', 'Invalid market trade'];
        return back()->withNotify($notify)->withInput();
        }
        if(!$escrow){
        $notify[] = ['error', 'No fund in escrow or escrow account not found'];
        return back()->withNotify($notify)->withInput();
        }

        $now = Carbon::now();
        if( $trade->expire < $now){
         $notify[] = ['error', 'Trade closed by payment window timer. You can no longer continue this trade'];
        return back()->withNotify($notify)->withInput();
        }


        if(!$wallet){
        $notify[] = ['error', 'Invalid buyer wallet or wallet address not found'];
        return back()->withNotify($notify)->withInput();
        }

         if($trade->status == 1){
         $notify[] = ['error', 'You have already approved this trade'];
            return back()->withNotify($notify);
        }
        $trade->status = 1;
        $trade->disbursed = 1;
        $trade->save();

        $wallet->balance += $escrow->amount;
        $notify[] = ['success', 'You have approved this trade, Coin disbursed to user'];
        return back()->withNotify($notify)->withInput();

    }


      public function p2plog()
    {

        $data['trade'] = Cryptotrade::whereBuyer(Auth::id())->orderBy('id','desc')->get();
         $data['page_title'] = "P2P Trade History";

        return view('user.offers.p2p-log', $data);

    }

       public function chathistory($id)
    {
        $trx = $id;
        $data['trade'] = Cryptotrade::whereBuyer(Auth::id())->where('trx', $trx)->orderBy('id','desc')->first();
        $data['offer'] = Cryptoffer::whereCode($data['trade']->marketcode)->orderBy('id','desc')->first();
        $data['coin'] = Currency::whereId($data['offer']->coin_id)->orderBy('id','desc')->first();
        $data['chat'] = Cryptotradechat::whereTradecode($data['trade']->trx)->orderBy('id','desc')->get();
        if(! $data['offer']){
        $notify[] = ['error', 'Invalid market offer'];
        return back()->withNotify($notify)->withInput();
        }
        $data['now'] = Carbon::now();
        $data['page_title'] = "Chet Seller";

        return view('user.offers.chatbuyer', $data);
    }


         public function tradedispute($id)
    {
        $trade = Cryptotrade::whereBuyer(Auth::id())->where('trx', $id)->orderBy('id','desc')->first();

        if(! $trade){
        $notify[] = ['error', 'Invalid market trade'];
        return back()->withNotify($notify)->withInput();
        }
        $trade->dispute = 1;
        $trade->save();
        $notify[] = ['success', 'You have initiated a dispute for this tradey'];
        return back()->withNotify($notify)->withInput();

    }

         public function closedispute($id)
    {
        $trade = Cryptotrade::whereBuyer(Auth::id())->where('trx', $id)->orderBy('id','desc')->first();

        if(! $trade){
        $notify[] = ['error', 'Invalid market trade'];
        return back()->withNotify($notify)->withInput();
        }
        $trade->dispute = 0;
        $trade->save();
        $notify[] = ['success', 'You have closed the dispute for this tradey'];
        return back()->withNotify($notify)->withInput();

    }














}
