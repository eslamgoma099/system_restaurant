<?php
namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with(['branch', 'locations', 'loyaltyPoints'])->get();
        return response()->json($customers);
    }
    public function getCoordinatesFromAddress($address)
    {
        $apiKey = '3aeb06a93b99446e898913ed3d92c405'; // مفتاح API من OpenCage
        $url = "https://api.opencagedata.com/geocode/v1/json?q=" . urlencode($address) . "&key={$apiKey}&language=ar";

        try {
            $response = \Illuminate\Support\Facades\Http::get($url);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['results'][0]['geometry'])) {
                    $components = $data['results'][0]['components'] ?? [];

                    // التحقق من أن الدولة هي مصر
                    if (($components['country'] ?? '') !== 'مصر' && ($components['country_code'] ?? '') !== 'eg') {
                        return null; // ليس داخل مصر
                    }

                    return [
                        'latitude' => $data['results'][0]['geometry']['lat'],
                        'longitude' => $data['results'][0]['geometry']['lng'],
                    ];
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
    public function store(Request $request)
    {
        // التحقق من البيانات المدخلة
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:customers,phone',
            'email' => 'nullable|email|unique:customers,email',
            'branch_id' => 'required|exists:branches,id',
            'locations' => 'required|array',
            'locations.*.address' => 'required|string',  // العنوان يجب أن يكون موجودًا
        ]);

        // إنشاء العميل
        $customer = Customer::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'branch_id' => $data['branch_id'],
        ]);
        $coordinates = $this->getCoordinatesFromAddress($data['locations'][0]['address']);

        // إضافة العنوان إلى جدول customer_locations
        foreach ($data['locations'] as $location) {
            $customer->locations()->create([
                'address' => $location['address'],
               'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],

            ]);
        }

        // تحميل العلاقات (العنوان)
        $customer->load('locations');

        // العودة بالرد
        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        $customer->load('branch', 'locations', 'loyaltyPoints');
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:customers,phone,' . $customer->id,
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'branch_id' => 'required|exists:branches,id',
        ]);

        $customer->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'branch_id' => $data['branch_id'],
        ]);

        $customer->load('branch', 'locations', 'loyaltyPoints');
        return response()->json($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(['message' => 'Customer deleted successfully']);
    }

    public function addLoyaltyPoints(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'points' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $loyaltyPoint = $customer->loyaltyPoints()->create([
            'points' => $data['points'],
            'description' => $data['description'] ?? 'نقاط مكتسبة يدويًا',
        ]);

        $customer->load('loyaltyPoints');
        return response()->json([
            'message' => 'تم إضافة النقاط بنجاح',
            'loyalty_point' => $loyaltyPoint,
            'customer' => $customer,
        ], 201);
    }
}