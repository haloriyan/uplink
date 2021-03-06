<?php

namespace App\Http\Controllers;

use Str;
use Log;
use Mail;
use Carbon\Carbon;
use App\Models\Visitor;
use App\Models\VisitorOrder;
use App\Models\VisitorOrderDetail;
use App\Models\Faq;
use App\Models\Link;
use App\Models\LinkStat;
use App\Models\User;

use App\Mail\EventReceipt;
use App\Mail\SupportReceipt;
use App\Mail\PaymentComplete;
use App\Mail\DigitalProductReceipt;

use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function me(Request $request) {
        $token = $request->token;
        $visitor = Visitor::where('token', $token)->first();

        return response()->json(['data' => $visitor,'token' => $token]);
    }
    public function check(Request $request) {
        $token = $request->token;
        $userID = $request->user_id;
        
        $users = Visitor::where('token', $token)->get();
        $isDataFound = false;
        $isRegistered = false;
        $newToken = null;

        if ($users->count() != 0) {
            $isRegistered = true;
            foreach ($users as $key => $item) {
                if ($item->user_id == $userID) {
                    $isDataFound = $key; // data found no need to register
                }
            }

            if ($isDataFound === false) {
                $newToken = Str::random(32);
                $registering = Visitor::create([
                    'token' => $newToken,
                    'user_id' => $userID,
                    'name' => $users[0]->name,
                    'email' => $users[0]->email,
                    'phone' => $users[0]->phone,
                    'address' => $users[0]->address,
                ]); // data exist but with different userID
            }
        }

        return response()->json([
            'isDataFound' => $isDataFound,
            'token' => $newToken,
            'isRegistered' => $isRegistered
        ]);
    }
    public function register(Request $request) {
        $token = Str::random(32);
        $saveData = Visitor::create([
            'user_id' => $request->profile_id,
            'name' => $request->name,
            'token' => $token
        ]);

        return response()->json([
            'data' => $saveData,
            'status' => 200,
            'token' => $token
        ]);
    }
    public function visitLink($id, Request $request) {
        $today = Carbon::now()->format('Y-m-d');
        $token = $request->token;
        $visitor = Visitor::where('token', $token)->first();
        
        $data = LinkStat::where([
            ['link_id', $id],
            ['visitor_id', $visitor->id],
            ['date', $today]
        ])->with('link');

        $stat = $data->first();
        if ($stat == "") {
            $createData = LinkStat::create([
                'link_id' => $id,
                'visitor_id' => $visitor->id,
                'date' => $today,
                'count' => 1
            ]);
            $link = Link::where('id', $id)->first();
        } else {
            $data->increment('count');
            $link = $stat->link;
        }

        return response()->json([
            'status' => 200,
        ]);
    }
    public function update(Request $request) {
        $data = Visitor::where('id', $request->visitor_id);
        $visitor = $data->first();
        $updateData = $data->update([
            'name' => $request->name,
            'email' => $request->email,
            'address' => $request->address,
        ]);
        
        return response()->json([
            'status' => 200,
            'message' => "Berhasil mengubah data visitor " . $visitor->name
        ]);
    }
    public function transactions(Request $request) {
        $token = $request->token;
        $username = $request->username;
        $user = User::where('username', $username)->first();

        $visitor = Visitor::where('token', $token)->with('transactions')
        ->whereHas('transactions', function ($query) use($user) {
            $query->where([
                ['is_placed', 1],
                ['user_id', $user->id]
            ]);
        })
        ->first();

        return response()->json([
            'status' => 200,
            'visitor' => $visitor
        ]);
    }
    public function transactionDetail($id, Request $request) {
        $data = VisitorOrder::where('id', $id)
        ->with(['details','visitor'])
        ->first();

        $details = $data->details;
        foreach ($details as $item) {
            $productType = $item->product_type;
            $classModel = CartController::$classToCall[$productType];
            $className = $classModel['name'];
            $queryProduct = $className::where('id', $item->{$productType});
            if (array_key_exists('relation', $classModel)) {
                $queryProduct = $queryProduct->with($classModel['relation']);
            }
            $item->product = $queryProduct->first();
        }

        return $data;
        // return response()->json()
    }
    public function paymentCallbacks($channel, $action = null, Request $request) {
        $amount = $request->amount;
        $referenceID = null;
        $paymentID = null;

        if ($channel == 'ewallet') {
            $data = $request->data;
            $referenceID = $data['reference_id'];
            $cartQuery = VisitorOrder::where('payment_reference_id', $referenceID);
        } else if ($channel == 'fva') {
            // $paymentID = $request->payment_id;
            $paymentID = "6239f8b6ac916538129aa24f";
            $cartQuery = VisitorOrder::where('payment_id', $paymentID);
        }
        
        $productQueue = [];
        if ($action == 'paid') {
            $updateCart = $cartQuery->update(['payment_status' => 'SUCCEEDED']);
            $cart = $cartQuery->with(['user','visitor','details'])->first();
            $user = $cart->user;
            $visitor = $cart->visitor;

            $classToCall = CartController::$classToCall;
            foreach ($cart->details as $item) {
                $productType = $item->product_type;
                $classModel = $classToCall[$productType];
                $className = $classModel['name'];
                $queryProduct = $className::where('id', $item->{$productType});
                if (array_key_exists('relation', $classModel)) {
                    $queryProduct = $queryProduct->with($classModel['relation']);
                }
                $product = $queryProduct->first();
                array_push($productQueue, [
                    'id' => $item->id,
                    'product' => $product
                ]);
                $item->product = $product;
            }

            $sendMail = Mail::to($user->email)->send(new PaymentComplete([
                'user' => $user,
                'cart' => $cart,
                'classToCall' => CartController::$classToCall
            ]));

            $sendMailDetail = $this->sendMailDetail($productQueue);
        }

        return response()->json([
            'message' => "Halo ".$channel,
            'reference_id' => $referenceID,
            'payment_id' => $paymentID
        ]);
    }
    public function sendMailDetail($queue) {
        foreach ($queue as $i => $item) {
            $query = VisitorOrderDetail::where('id', $item['id']);
            $detail = $query->with(['order.visitor','order.user'])->first();
            $visitor = $detail->order->visitor;
            $user = $detail->order->user;

            if ($detail->product_type == 'event') {
                $event = EventController::getByID($detail->event, true);
                $sendReceipt = Mail::to($visitor->email)->send(new EventReceipt([
                    'event' => $event,
                    'visitor' => $visitor,
                    'user' => $user,
                ]));
            } else if ($detail->product_type == 'digital_product') {
                $product = DigitalProductController::getByID($detail->digital_product, true);
                $sendReceipt = Mail::to($visitor->email)->send(new DigitalProductReceipt([
                    'prduct' => $prduct,
                    'user' => $user,
                    'visitor' => $visitor,
                ]));
            } else if ($detail->product_type == 'support') {
                $support = SupportController::getByID($detail->support, true);
                $sendReceipt = Mail::to($visitor->email)->send(new SupportReceipt([
                    'support' => $support,
                    'visitor' => $visitor,
                    'user' => $user,
                ]));
            }

            Log::info('detail : ' . json_encode($detail));
        }
    }
    public function statistic(Request $request) {
        $token = $request->token;
        $now = Carbon::now();
        
        $user = UserController::get($token)->first();
        $query = Visitor::where('user_id', $user->id);
        $customers = $query->get('id');

        $thisMonth = $query->whereBetween('created_at', [
            $now->startOfMonth()->format('Y-m-d'),
            $now->endOfMonth()->format('Y-m-d')
        ])->get();

        return response()->json([
            'status' => 200,
            'customers' => $customers,
            'thisMonth' => $thisMonth,
        ]);
    }
    public function paycom() {
        $cart = VisitorOrder::where('id', 14)->with(['user','visitor','details','voucher'])->first();
        $classToCall = CartController::$classToCall;
        foreach ($cart->details as $item) {
            $productType = $item->product_type;
            $classModel = $classToCall[$productType];
            $className = $classModel['name'];
            $queryProduct = $className::where('id', $item->{$productType});
            if (array_key_exists('relation', $classModel)) {
                $queryProduct = $queryProduct->with($classModel['relation']);
            }
            $item->product = $queryProduct->first();
        }
        
        return new PaymentComplete([
            'cart' => $cart,
            'classToCall' => $classToCall
        ]);
    }
    public function faq() {
        $faqs = Faq::orderBy('updated_at', 'DESC')->get();
        return response()->json($faqs);
    }
    public function webreg() {
        $user = UserController::getByID(19)->first();
        return new \App\Mail\RegisterByWeb($user);
    }
}
