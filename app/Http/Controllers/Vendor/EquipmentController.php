<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\EquipmentBooking;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class EquipmentController extends Controller
{
    public function index(Request $request)
    {
        $storeId = Helpers::get_store_id();
        $status = $request->query('status');
        $search = $request->query('search');

        $equipment = Equipment::with('item')
            ->whereHas('item', function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            })
            ->when($status && in_array($status, ['available', 'maintenance', 'retired']), fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->whereHas('item', function ($itemQ) use ($search) {
                $itemQ->where('name', 'like', "%{$search}%");
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'));

        return view('vendor-views.equipment.index', compact('equipment', 'status', 'search'));
    }

    public function create()
    {
        $storeId = Helpers::get_store_id();
        $moduleId = Helpers::get_store_data()->module_id;

        $categories = Category::where(['position' => 0])->module($moduleId)->get();
        $units = Unit::orderBy('created_at')->get(['id', 'unit']);
        $store_categories = Helpers::storeCategoryStatus()
            ? \App\Models\StoreCategory::active()->where('store_id', $storeId)->orderBy('priority', 'desc')->get(['id', 'name'])
            : collect();

        return view('vendor-views.equipment.create', compact('categories', 'units', 'store_categories'));
    }

    public function store(Request $request)
    {
        $this->validateEquipment($request);

        if (!Helpers::get_store_data()->item_section) {
            Toastr::warning(translate('messages.permission_denied'));
            return back();
        }

        $store = Helpers::get_store_data();

        DB::transaction(function () use ($request, $store) {
            $data = $this->equipmentPayload($request, $store);

            $item = new Item();
            $item->name = $request->name[array_search('default', $request->lang)];
            $item->description = $request->description[array_search('default', $request->lang)];
            $item->category_id = $request->category_id;
            $item->category_ids = json_encode([['id' => $request->category_id, 'position' => 1]]);
            $item->unit_id = $request->filled('unit') ? $request->unit : null;
            $item->price = $data['price'];
            $item->image = $request->has('image') ? Helpers::upload('product/', 'png', $request->file('image')) : null;
            $item->store_id = $store->id;
            $item->module_id = $store->module_id;
            $item->store_category_id = $request->filled('store_category_id') ? (int) $request->store_category_id : null;
            $item->stock = $request->stock;
            $item->status = $request->status === 'available' ? 1 : 0;
            $item->is_approved = 1;
            $item->available_time_starts = '00:00:00';
            $item->available_time_ends = '23:59:59';
            $item->save();

            Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'Item', data_id: $item->id, data_value: $item->name);
            Helpers::add_or_update_translations(request: $request, key_data: 'description', name_field: 'description', model_name: 'Item', data_id: $item->id, data_value: $item->description);

            Equipment::create(array_merge($data['equipment'], [
                'item_id' => $item->id,
            ]));
        });

        Toastr::success(translate('Equipment added successfully.'));
        return redirect()->route('vendor.equipment.index');
    }

    public function edit($id)
    {
        $storeId = Helpers::get_store_id();
        $moduleId = Helpers::get_store_data()->module_id;

        $equipment = Equipment::with('item')
            ->whereHas('item', fn ($q) => $q->where('store_id', $storeId))
            ->findOrFail($id);

        $categories = Category::where(['position' => 0])->module($moduleId)->get();
        $units = Unit::orderBy('created_at')->get(['id', 'unit']);
        $store_categories = Helpers::storeCategoryStatus()
            ? \App\Models\StoreCategory::active()->where('store_id', $storeId)->orderBy('priority', 'desc')->get(['id', 'name'])
            : collect();

        return view('vendor-views.equipment.edit', compact('equipment', 'categories', 'units', 'store_categories'));
    }

    public function update(Request $request, $id)
    {
        $this->validateEquipment($request);

        $storeId = Helpers::get_store_id();
        $equipment = Equipment::with('item')
            ->whereHas('item', fn ($q) => $q->where('store_id', $storeId))
            ->findOrFail($id);

        $store = Helpers::get_store_data();

        DB::transaction(function () use ($request, $store, $equipment) {
            $data = $this->equipmentPayload($request, $store);

            $item = $equipment->item;
            $item->name = $request->name[array_search('default', $request->lang)];
            $item->description = $request->description[array_search('default', $request->lang)];
            $item->category_id = $request->category_id;
            $item->category_ids = json_encode([['id' => $request->category_id, 'position' => 1]]);
            $item->unit_id = $request->filled('unit') ? $request->unit : null;
            $item->price = $data['price'];
            if ($request->has('image')) {
                if ($item->image && Storage::disk('public')->exists("product/{$item->image}")) {
                    Storage::disk('public')->delete("product/{$item->image}");
                }
                $item->image = Helpers::upload('product/', 'png', $request->file('image'));
            }
            $item->store_category_id = $request->filled('store_category_id') ? (int) $request->store_category_id : null;
            $item->stock = $request->stock;
            $item->status = $request->status === 'available' ? 1 : 0;
            $item->save();

            $item->translations()->delete();
            Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'Item', data_id: $item->id, data_value: $item->name);
            Helpers::add_or_update_translations(request: $request, key_data: 'description', name_field: 'description', model_name: 'Item', data_id: $item->id, data_value: $item->description);

            $equipment->fill($data['equipment'])->save();
        });

        Toastr::success(translate('Equipment updated successfully.'));
        return redirect()->route('vendor.equipment.index');
    }

    public function destroy($id)
    {
        $storeId = Helpers::get_store_id();
        $equipment = Equipment::with('item')
            ->whereHas('item', fn ($q) => $q->where('store_id', $storeId))
            ->findOrFail($id);

        if (EquipmentBooking::where('item_id', $equipment->item_id)->exists()) {
            Toastr::warning(translate('Cannot delete equipment with booking history.'));
            return back();
        }

        $item = $equipment->item;
        $equipment->delete();
        if ($item) {
            if ($item->image && Storage::disk('public')->exists("product/{$item->image}")) {
                Storage::disk('public')->delete("product/{$item->image}");
            }
            $item->delete();
        }

        Toastr::success(translate('Equipment deleted successfully.'));
        return back();
    }

    public function status($id, $status)
    {
        $storeId = Helpers::get_store_id();
        $equipment = Equipment::with('item')
            ->whereHas('item', fn ($q) => $q->where('store_id', $storeId))
            ->findOrFail($id);

        if (!in_array($status, ['available', 'maintenance'])) {
            Toastr::warning(translate('Invalid status.'));
            return back();
        }

        $equipment->status = $status;
        $equipment->save();
        if ($equipment->item) {
            $equipment->item->status = $status === 'available' ? 1 : 0;
            $equipment->item->save();
        }

        Toastr::success(translate('Equipment status updated.'));
        return back();
    }

    protected function validateEquipment(Request $request)
    {
        $request->validate([
            'name' => 'required|array',
            'name.0' => 'required|max:191',
            'name.*' => 'max:191',
            'description' => 'required|array',
            'description.0' => 'required|max:1000',
            'description.*' => 'max:1000',
            'category_id' => 'required|exists:categories,id',
            'store_category_id' => 'nullable|exists:store_categories,id',
            'image' => 'required_if:equipment_id,null|image|max:' . MAX_FILE_SIZE * 1024,
            'stock' => 'required|integer|min:1',
            'hourly_rate' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'monthly_rate' => 'nullable|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
            'min_rental_duration' => 'required|integer|min:1',
            'max_rental_duration' => 'nullable|integer|min:1',
            'requires_delivery' => 'nullable|boolean',
            'self_pickup' => 'nullable|boolean',
            'condition_notes' => 'nullable|string|max:2000',
            'operator_included' => 'nullable|boolean',
            'operator_fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:available,maintenance',
        ]);
    }

    protected function equipmentPayload(Request $request, $store)
    {
        $rates = [
            'hourly' => $request->filled('hourly_rate') ? (float) $request->hourly_rate : null,
            'daily' => $request->filled('daily_rate') ? (float) $request->daily_rate : null,
            'weekly' => $request->filled('weekly_rate') ? (float) $request->weekly_rate : null,
            'monthly' => $request->filled('monthly_rate') ? (float) $request->monthly_rate : null,
        ];

        $positiveRates = array_filter($rates, fn ($rate) => $rate > 0);

        if (count($positiveRates) === 0) {
            abort(422, translate('At least one rental rate must be set.'));
        }

        $operatorIncluded = (bool) $request->input('operator_included', false);

        return [
            'price' => min($positiveRates),
            'equipment' => [
                'hourly_rate' => $rates['hourly'],
                'daily_rate' => $rates['daily'],
                'weekly_rate' => $rates['weekly'],
                'monthly_rate' => $rates['monthly'],
                'security_deposit' => $request->filled('security_deposit') ? $request->security_deposit : 0,
                'min_rental_duration' => $request->min_rental_duration,
                'max_rental_duration' => $request->filled('max_rental_duration') ? $request->max_rental_duration : null,
                'requires_delivery' => (bool) $request->input('requires_delivery', false),
                'self_pickup' => (bool) $request->input('self_pickup', true),
                'condition_notes' => $request->condition_notes,
                'operator_included' => $operatorIncluded,
                'operator_fee' => $operatorIncluded ? $request->operator_fee : null,
                'status' => $request->status,
            ],
        ];
    }
}
