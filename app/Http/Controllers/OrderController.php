<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Item;
use App\Models\Inventory;
use App\Models\Account;
use Illuminate\Http\Request;
use App\Models\Offer;
use App\Models\LoyaltyPoint;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Shift;
use App\Models\Refund; // أضف هذا السطر لاستيراد الكلاس Refund
use Illuminate\Support\Facades\Http;
use App\Services\PaymobService;

class OrderController extends Controller
{
      public function store(Request $request)
    {
        // $this->middleware('role:employee,cashier,admin');

        $data = $request->validate([
            'table_number' => 'nullable|integer',
            'order_type' => 'required|in:takeaway,delivery,dine_in',
            'customer_location_id' => 'required_if:order_type,delivery|exists:customer_locations,id',
            'items' => 'required|array',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'addons' => 'nullable|array|max:3',
            'addons.*.ingredient_id' => 'required_with:addons|exists:ingredients,id',
            'addons.*.quantity' => 'required_with:addons|integer|min:1|max:3',
            'confirm_addons' => 'nullable|boolean',
        ]);

        if ($data['order_type'] === 'dine_in' && !isset($data['table_number'])) {
            return response()->json(['message' => trans('orders.table_number_required')], 400);
        }

        if ($data['order_type'] !== 'dine_in' && isset($data['table_number'])) {
            return response()->json(['message' => trans('orders.table_number_not_allowed')], 400);
        }

        $customerLocation = null;
        if (isset($data['customer_location_id'])) {
            $customerLocation = \App\Models\CustomerLocation::findOrFail($data['customer_location_id']);
        }

        $branchId = auth()->user()->branch_id;
        if (!$branchId) {
            return response()->json(['message' => 'المستخدم غير مرتبط بفرع'], 400);
        }

        $order = Order::create([
            'branch_id' => $branchId,
            'order_type' => $data['order_type'],
            'table_number' => $data['table_number'] ?? null,
            'customer_location_id' => $data['customer_location_id'] ?? null,
            'status' => 'pending',
            'payment_status' => 'pending',
            'employee_id' => auth()->id(),
            'cashier_id' => auth()->id(),
        ]);

        $totalPrice = 0;
        $deliveryFee = 0;
        $activeOffers = Offer::where('branch_id', $branchId)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        $itemsGrouped = [];
        foreach ($data['items'] as $itemData) {
            $itemId = $itemData['item_id'];
            if (!isset($itemsGrouped[$itemId])) {
                $itemsGrouped[$itemId] = [
                    'item_id' => $itemId,
                    'quantity' => 0,
                ];
            }
            $itemsGrouped[$itemId]['quantity'] += $itemData['quantity'];
        }

        $orderItems = [];
        foreach ($itemsGrouped as $itemData) {
            $item = \App\Models\Item::find($itemData['item_id']);
            if (!$item) {
                continue;
            }
            $ingredients = \App\Models\ItemIngredient::where('item_id', $item->id)->get();
            foreach ($ingredients as $ingredient) {
                $totalToDeduct = $ingredient->quantity * $itemData['quantity'];

                $inventory = \App\Models\Inventory::firstOrCreate(
                    [
                        // 'item_id' => $itemData['item_id'],
                        'ingredient_id' => $ingredient->ingredient_id,
                        'branch_id' => $branchId,
                    ],
                    [
                        'quantity' => 100,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                if ($inventory->quantity >= $totalToDeduct) {
                    $inventory->decrement('quantity', $totalToDeduct);
                } else {
                    $order->delete();
                    return response()->json(['message' => trans('orders.insufficient_stock', ['name' => $item->name])], 400);
                }
            }

            $subTotal = $item->price * $itemData['quantity'];

            if ($activeOffers) {
                $discount = $subTotal * ($activeOffers->discount_percentage / 100);
                $subTotal -= $discount;
            }

            $totalPrice += $subTotal;

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'item_id' => $item->id,
                'quantity' => $itemData['quantity'],
                'price' => $item->price,
            ]);

            $orderItems[$item->id] = $orderItem;
        }

        $unavailableAddons = [];
        $groupedAddons = [];
        $hasUnavailableAddons = false;

        if (isset($data['addons']) && is_array($data['addons'])) {
            foreach ($data['addons'] as $addon) {
                $ingredientId = $addon['ingredient_id'];
                if (!isset($groupedAddons[$ingredientId])) {
                    $groupedAddons[$ingredientId] = [
                        'ingredient_id' => $ingredientId,
                        'quantity' => 0,
                    ];
                }
                $groupedAddons[$ingredientId]['quantity'] += $addon['quantity'];
            }

            $defaultOrderItem = reset($orderItems);
            if (!$defaultOrderItem) {
                $order->delete();
                return response()->json(['message' => 'لا يمكن إضافة إضافات بدون عناصر في الطلب'], 400);
            }

            foreach ($groupedAddons as $addon) {
                $ingredient = \App\Models\Ingredient::findOrFail($addon['ingredient_id']);
                $addonPrice = $ingredient->cost_per_unit;

                $addonInventory = \App\Models\Inventory::firstOrCreate(
                    [
                        'branch_id' => $order->branch_id,
                        'ingredient_id' => $ingredient->id,
                    ],
                    [
                        'quantity' => 100,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                if ($addonInventory->quantity >= $addon['quantity']) {
                    $addonInventory->decrement('quantity', $addon['quantity']);

                    \App\Models\OrderAddon::create([
                        'order_id' => $order->id,
                        'ingredient_id' => $addon['ingredient_id'],
                        'addon_price' => $addonPrice,
                        'quantity' => $addon['quantity'],
                        'order_item_id' => $defaultOrderItem->id,
                    ]);
                    $totalPrice += $addonPrice * $addon['quantity'];
                } else {
                    $reason = "الكمية غير كافية، متبقي: {$addonInventory->quantity}";
                    $unavailableAddons[] = "{$ingredient->name} ({$reason})";
                    $hasUnavailableAddons = true;
                }
            }
        }

        if ($hasUnavailableAddons && !$request->boolean('confirm_addons')) {
            return response()->json([
                'message' => trans('orders.confirm_missing_addons'),
                'unavailable_addons' => array_unique($unavailableAddons),
                'require_confirmation' => true
            ], 422);
        }

        if ($hasUnavailableAddons && $request->boolean('confirm_addons')) {
            $defaultOrderItem = reset($orderItems);
            if (!$defaultOrderItem) {
                $order->delete();
                return response()->json(['message' => 'لا يمكن إضافة إضافات بدون عناصر في الطلب'], 400);
            }

            foreach ($groupedAddons as $addon) {
                $ingredient = \App\Models\Ingredient::findOrFail($addon['ingredient_id']);
                $addonPrice = $ingredient->cost_per_unit;

                \App\Models\OrderAddon::create([
                    'order_id' => $order->id,
                    'ingredient_id' => $addon['ingredient_id'],
                    'addon_price' => $addonPrice,
                    'quantity' => $addon['quantity'],
                    'order_item_id' => $defaultOrderItem->id,
                ]);

                $totalPrice += $addonPrice * $addon['quantity'];
            }
        }

        if ($data['order_type'] === 'delivery') {
            $branch = \App\Models\Branch::findOrFail($order->branch_id);
            $customerLocation = \App\Models\CustomerLocation::findOrFail($order->customer_location_id);

            $pricePerKm = $branch->price_per_km ?? 2.0;
            $maxDistance = $branch->max_delivery_distance ?? 10.0;

            // Check if coordinates exist
            if (is_null($branch->latitude) || is_null($branch->longitude)) {
                $order->delete();
                return response()->json([
                    'message' => 'إحداثيات الفرع غير متوفرة في قاعدة البيانات.',
                ], 500);
            }

            if (is_null($customerLocation->latitude) || is_null($customerLocation->longitude)) {
                $order->delete();
                return response()->json([
                    'message' => 'إحداثيات موقع العميل غير متوفرة في قاعددة البيانات.',
                ], 500);
            }

            // Use coordinates directly from the database
            $latFrom = deg2rad($branch->latitude);
            $lonFrom = deg2rad($branch->longitude);
            $latTo = deg2rad($customerLocation->latitude);
            $lonTo = deg2rad($customerLocation->longitude);

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = 6371 * $c; // Distance in kilometers

            if ($distance > $maxDistance) {
                $order->delete();
                return response()->json([
                    'message' => "الموقع خارج نطاق التوصيل المسموح ({$maxDistance} كم)",
                    'actual_distance_km' => round($distance, 2)
                ], 422);
            }

            $deliveryFee = $distance * $pricePerKm;
            $totalPrice += $deliveryFee;
        }

        if ($data['order_type'] === 'dine_in') {
            $serviceFee = $totalPrice * 0.10;
            $totalPrice += $serviceFee;
        }

        if ($customerLocation) {
            $customer = $customerLocation->customer;
            if ($customer) {
                $pointsEarned = floor($totalPrice / 10);
                \App\Models\LoyaltyPoint::create([
                    'customer_id' => $customer->id,
                    'points' => $pointsEarned,
                    'description' => "نقاط مكتسبة من الطلب رقم {$order->id}",
                ]);
            }
        }

        $order->update(['total_price' => $totalPrice]);

        $order->load('items', 'addons', 'customerLocation');
        $message = !empty($unavailableAddons)
            ? trans('orders.some_addons_unavailable', ['names' => implode(', ', array_unique($unavailableAddons))])
            : trans('orders.created');

        // بعد إنشاء الطلب وحفظ الأصناف المرتبطة به
        // foreach ($order->orderItems ?? [] as $orderItem) {

        //     foreach ($orderItem->ingredients as $ingredient) {
        //         // الكمية المطلوبة من هذا المكوّن لهذا الصنف في الطلب
        //         $requiredQuantity = $ingredient->pivot->quantity * $orderItem->quantity;

        //         // تحديث المخزون
        //         $inventory = \App\Models\Inventory::where('branch_id', $order->branch_id)
        //             ->where('ingredient_id', $ingredient->id)
        //             ->first();

        //         if ($inventory) {
        //             $inventory->decrement('quantity', $requiredQuantity);

        //             // (اختياري) إرسال تنبيه إذا المخزون أصبح قليل
        //             if ($inventory->quantity <= 10) {
        //                 // إرسال إشعار أو بريد إلكتروني
        //                 // auth()->user()->notify(new \App\Notifications\LowStockNotification($ingredient->name, $inventory->quantity));
        //             }
        //         }
        //     }
        // }

        return response()->json([
            'message' => $message,
            'unavailable_addons' => array_unique($unavailableAddons),
            'delivery_fee' => $deliveryFee,
            'order' => new \App\Http\Resources\OrderResource($order)
        ], 201);
    }
    public function getCoordinatesFromAddress($address)
    {
        $apiKey = '3aeb06a93b99446e898913ed3d92c405'; // استبدل هذا بمفتاح API الخاص بك من OpenCage
        $url = "https://api.opencagedata.com/geocode/v1/json?q=" . urlencode($address) . "&key={$apiKey}&language=ar";

        try {
            $response = \Illuminate\Support\Facades\Http::get($url);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['results'][0]['geometry'])) {
                    $latitude = $data['results'][0]['geometry']['lat'];
                    $longitude = $data['results'][0]['geometry']['lng'];

                    return [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ];
                } else {
                    return response()->json([
                        'message' => 'تعذر الحصول على الإحداثيات من العنوان المحدد.'
                    ], 404);
                }
            } else {
                return response()->json([
                    'message' => 'حدث خطأ أثناء الاتصال بخدمة تحديد المواقع.'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'فشل في تنفيذ الطلب: ' . $e->getMessage()
            ], 500);
        }
    }


public function update(Request $request, $id)
{
    $this->middleware('role:employee,admin');

    $order = Order::findOrFail($id);

    $data = $request->validate([
        'status' => 'sometimes|in:pending,in_progress,completed',
    ]);

    // نضيفها يدويًا
    $data['payment_status'] = 'paid';

    $order->update($data);

    return response()->json(['message' => 'Order updated', 'order' => $order]);
}


    public function destroy($id)
    {
        $this->middleware('role:admin');

        $order = Order::findOrFail($id);
        $order->delete();
        return response()->json(['message' => 'Order deleted']);
    }

    public function index()
    {
        $this->middleware('role:employee,admin,cashier');

        $orders = Order::where('branch_id', auth()->user()->branch_id)->with('items')->get();
        return response()->json(['orders' => $orders]);
    }
    public function makePayment(Request $request)
{
    $data = $request->validate([
        'order_id' => 'required|exists:orders,id',
        'payments' => 'required|array|min:1',
        'payments.*.payment_method' => 'required|in:cash,credit_card,online',
        'payments.*.amount' => 'required|numeric|min:0',
    ]);

    \Illuminate\Support\Facades\Log::info("Processing payment for order: {$data['order_id']}", $data);

    $order = Order::find($data['order_id']);

    if (!$order) {
        \Illuminate\Support\Facades\Log::error("Order not found: {$data['order_id']}");
        return response()->json(['message' => 'الطلب غير موجود'], 404);
    }

    $totalPrice = (float) $order->total_price;
    $previouslyPaid = $order->payments()->sum('amount');
    $remainingAmount = $totalPrice - $previouslyPaid;

    if ($remainingAmount <= 0) {
        \Illuminate\Support\Facades\Log::warning("Order already fully paid: {$order->id}");
        return response()->json(['message' => 'الطلب تم دفعه بالكامل مسبقًا'], 400);
    }

    $amountPaidInThisRequest = collect($data['payments'])->sum('amount');

    if ($amountPaidInThisRequest < $remainingAmount) {
        $stillRemaining = $remainingAmount - $amountPaidInThisRequest;
        \Illuminate\Support\Facades\Log::warning("Insufficient payment for order: {$order->id}. Remaining: {$stillRemaining}");
        return response()->json([
            'message' => "المبلغ المدفوع أقل من المتبقي. المتبقي: $stillRemaining. الرجاء إكمال الدفع.",
        ], 400);
    }

    // جمع أنواع الدفع من هذه العملية
    $newPaymentMethods = collect($data['payments'])->pluck('payment_method')->unique()->toArray();

    // جمع أنواع الدفع من الدفعات السابقة (إن وجدت)
    $existingPaymentMethods = $order->payments()->pluck('payment_method')->unique()->toArray();

    // دمج أنواع الدفع (السابقة والجديدة) وإزالة التكرارات
    $allPaymentMethods = array_unique(array_merge($existingPaymentMethods, $newPaymentMethods));

    // تحويل أنواع الدفع إلى سلسلة مفصولة بفواصل
    $paymentMethodString = implode(',', $allPaymentMethods);

    // إنشاء دفعة منفصلة لكل طريقة دفع
    $createdPayments = [];
    foreach ($data['payments'] as $paymentData) {
        $payment = Payment::create([
            'order_id' => $order->id,
            'cashier_id' => auth()->id(),
            'amount' => $paymentData['amount'],
            'payment_method' => $paymentData['payment_method'],
            'payment_date' => now(),
            'branch_id' => $order->branch_id,
        ]);
        $createdPayments[] = $payment;
    }

    // تحديث حالة الطلب ونوع الدفع
    $newTotalPaid = $previouslyPaid + $amountPaidInThisRequest;
    $updateData = [
        'payment_method' => $paymentMethodString,
    ];

    if ($newTotalPaid >= $totalPrice) {
        $updateData['payment_status'] = 'paid';
        $updateData['status'] = 'completed';
    } else {
        $updateData['payment_status'] = 'partially_paid';
    }

    $order->update($updateData);

    \Illuminate\Support\Facades\Log::info("Payment successful for order: {$order->id}", [
        'total_paid' => $newTotalPaid,
        'remaining' => max(0, $totalPrice - $newTotalPaid),
        'payment_methods' => $paymentMethodString,
    ]);

    return response()->json([
        'message' => 'تم تسجيل الدفع بنجاح',
        'payments' => $createdPayments,
        'order' => new \App\Http\Resources\OrderResource($order),
    ], 201);
}


    public function printOrder($id)
    {
        $this->middleware('role:employee,cashier,admin');

        $order = Order::with('items.item')->findOrFail($id);

        $printData = [
            'order_id' => $order->id,
            'table_number' => $order->table_number,
            'status' => $order->status,
            'items' => $order->items->map(function ($item) {
                return [
                    'name' => $item->item->name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ];
            }),
            'total_price' => $order->total_price,
            'destination' => $order->table_number ? 'Kitchen' : 'Bar',
            'printed_at' => now()->toDateTimeString(),
        ];

        // إنشاء PDF
        $pdf = Pdf::loadView('pdf.order', ['data' => $printData]);
        return $pdf->download("order_{$order->id}.pdf");
    }
    public function refundPayment(Request $request)
{
    $data = $request->validate([
        'order_id' => 'required|exists:orders,id',
        'amount' => 'required|numeric|min:0',
        'payment_method' => 'required|in:cash,credit_card,online',
        'reason' => 'nullable|string|max:500',
    ]);

    \Illuminate\Support\Facades\Log::info("Processing refund for order: {$data['order_id']}", $data);

    $order = Order::find($data['order_id']);

    if (!$order) {
        \Illuminate\Support\Facades\Log::error("Order not found: {$data['order_id']}");
        return response()->json(['message' => 'الطلب غير موجود'], 404);
    }

    // التحقق من أن الطلب تم دفع بعض المبالغ على الأقل
    $totalPaid = $order->payments()->sum('amount');
    $totalRefunded = $order->refunds()->sum('amount');

    $remainingPaid = $totalPaid - $totalRefunded;

    if ($remainingPaid <= 0) {
        \Illuminate\Support\Facades\Log::warning("Order has no remaining paid amount to refund: {$order->id}");
        return response()->json(['message' => 'لا توجد مبالغ متبقية للاسترجاع'], 400);
    }

    // التحقق من أن المبلغ المطلوب استرجاعه لا يتجاوز المبلغ المدفوع المتبقي
    if ($data['amount'] > $remainingPaid) {
        \Illuminate\Support\Facades\Log::warning("Refund amount exceeds remaining paid amount for order: {$order->id}. Requested: {$data['amount']}, Remaining: {$remainingPaid}");
        return response()->json([
            'message' => "المبلغ المطلوب استرجاعه أكبر من المبلغ المدفوع المتبقي. المتبقي: $remainingPaid",
        ], 400);
    }

    // التحقق من أن طريقة الدفع المستخدمة في الاسترجاع كانت مستخدمة في الدفع
    $usedPaymentMethods = $order->payments()->pluck('payment_method')->unique()->toArray();
    if (!in_array($data['payment_method'], $usedPaymentMethods)) {
        \Illuminate\Support\Facades\Log::warning("Payment method not used in order: {$order->id}. Requested: {$data['payment_method']}");
        return response()->json([
            'message' => "طريقة الدفع المحددة لم تُستخدم في الدفع الأصلي لهذا الطلب",
        ], 400);
    }

    // إنشاء سجل استرجاع
    $refund = Refund::create([
        'order_id' => $order->id,
        'cashier_id' => auth()->id(),
        'amount' => $data['amount'],
        'payment_method' => $data['payment_method'],
        'reason' => $data['reason'],
        'refund_date' => now(),
        'branch_id' => $order->branch_id,
    ]);

    // تحديث حالة الطلب بناءً على المبلغ المسترد
    $newRemainingPaid = $remainingPaid - $data['amount'];
    if ($newRemainingPaid <= 0) {
        $order->update([
            'payment_status' => 'refunded',
            'status' => 'cancelled', // أو أي حالة مناسبة
        ]);
    } else {
        $order->update([
            'payment_status' => 'partially_refunded',
        ]);
    }

    \Illuminate\Support\Facades\Log::info("Refund successful for order: {$order->id}", [
        'refund_amount' => $data['amount'],
        'remaining_paid' => $newRemainingPaid,
    ]);

    return response()->json([
        'message' => 'تم تسجيل الاسترجاع بنجاح',
        'refund' => $refund,
        'order' => new \App\Http\Resources\OrderResource($order),
    ], 201);
}

public function initiatePaymobPayment(Request $request)
{
    $data = $request->validate([
        'order_id' => 'required|exists:orders,id',
        'billing_data' => 'required|array',
        'billing_data.first_name' => 'required|string',
        'billing_data.last_name' => 'required|string',
        'billing_data.email' => 'required|email',
        'billing_data.phone_number' => 'required|string',
        'billing_data.apartment' => 'required|string',
        'billing_data.floor' => 'required|string',
        'billing_data.street' => 'required|string',
        'billing_data.building' => 'required|string',
        'billing_data.city' => 'required|string',
        'billing_data.state' => 'required|string',
        'billing_data.country' => 'required|string',
    ]);

    $order = Order::findOrFail($data['order_id']);

    if ($order->payment_status === 'paid') {
        return response()->json(['message' => 'Order is already paid'], 400);
    }

    $paymobService = new PaymobService();

    // Create Paymob order
    $paymobOrder = $paymobService->createOrder($order->total_price, $order->id);
    if (!$paymobOrder) {
        return response()->json(['message' => 'Failed to create payment order'], 500);
    }

    // Get payment key
    $paymentKey = $paymobService->getPaymentKey(
        $paymobOrder['id'],
        $order->total_price,
        $data['billing_data']
    );

    if (!$paymentKey) {
        return response()->json(['message' => 'Failed to generate payment key'], 500);
    }

    // Generate iframe URL
    $iframeUrl = "https://accept.paymob.com/api/acceptance/iframes/{$paymobService->getIframeId()}?payment_token={$paymentKey}";

    return response()->json([
        'iframe_url' => $iframeUrl,
        'order_id' => $order->id,
        'paymob_order_id' => $paymobOrder['id']
    ]);
}

public function paymobCallback(Request $request)
{
    $data = $request->all();

    // Verify HMAC signature
    $paymobService = new PaymobService();
    $calculatedHmac = hash_hmac('sha512', $data['obj']['order']['merchant_order_id'], $paymobService->getHmacSecret());

    if ($calculatedHmac !== $data['hmac']) {
        return response()->json(['message' => 'Invalid signature'], 400);
    }

    $order = Order::findOrFail($data['obj']['order']['merchant_order_id']);

    if ($data['obj']['success']) {
        // Create payment record
        Payment::create([
            'order_id' => $order->id,
            'cashier_id' => auth()->id(),
            'amount' => $order->total_price,
            'payment_method' => 'paymob',
            'payment_date' => now(),
            'branch_id' => $order->branch_id,
        ]);

        // Update order status
        $order->update([
            'payment_status' => 'paid',
            'status' => 'completed'
        ]);

        return response()->json(['message' => 'Payment successful']);
    }

    return response()->json(['message' => 'Payment failed'], 400);
}

}
