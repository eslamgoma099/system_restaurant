<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function store(Request $request)
    {
        // التحقق من البيانات المدخلة
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'price_per_km' => 'nullable|numeric|min:0',
            'max_delivery_distance' => 'nullable|numeric|min:0',
        ]);

        // استخراج الإحداثيات من العنوان
        $coordinates = $this->getCoordinatesFromAddress($data['location']);

        if (!$coordinates) {
            return response()->json([
                'message' => 'تعذر تحديد إحداثيات الموقع من العنوان.'
            ], 422);
        }

        // إنشاء الفرع
        $branch = Branch::create([
            'name' => $data['name'],
            'location' => $data['location'],
            'price_per_km' => $data['price_per_km'] ?? 2.00,
            'max_delivery_distance' => $data['max_delivery_distance'] ?? 10.00,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'super_admin_id' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'تم إنشاء الفرع بنجاح',
            'branch' => $branch
        ], 201);
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


    public function show($id)
    {
        // التحقق من صلاحيات المستخدم
        $this->authorize('view', Branch::class);

        // العثور على الفرع بواسطة الـ id
        $branch = Branch::findOrFail($id);

        return response()->json([
            'message' => 'تم جلب تفاصيل الفرع بنجاح',
            'branch' => $branch
        ]);
    }

    // دالة لتحديث بيانات الفرع
    public function update(Request $request, $id)
    {
        // التحقق من صلاحيات المستخدم
        $this->authorize('update', Branch::class);

        // التحقق من البيانات المدخلة
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'price_per_km' => 'nullable|numeric|min:0',
            'max_delivery_distance' => 'nullable|numeric|min:0',
        ]);

        // العثور على الفرع بواسطة الـ id
        $branch = Branch::findOrFail($id);

        // تحديث البيانات
        $branch->update([
            'name' => $data['name'],
            'location' => $data['location'],
            'price_per_km' => $data['price_per_km'] ?? $branch->price_per_km,
            'max_delivery_distance' => $data['max_delivery_distance'] ?? $branch->max_delivery_distance,
        ]);

        return response()->json([
            'message' => 'تم تحديث بيانات الفرع بنجاح',
            'branch' => $branch
        ]);
    }
}
