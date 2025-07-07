<?php

use App\Models\Models\Admin;
use Illuminate\Http\Request;
use App\Models\Models\Campaign;
use App\Models\Models\Customer;
use App\Models\Models\Transaction;
use Illuminate\Routing\Controller;
use App\Models\Models\StoreSetting;
use Illuminate\Support\Facades\Hash;

class LoyaltyController extends Controller
{
    public function adminLogin(Request $request)
    {
        $admin = Admin::where('username', $request->username)->first();
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        $admin->update(['last_login' => now()]);
        return response()->json(['success' => true, 'data' => [
            'token' => 'demo-admin-token',
            'user' => $admin
        ]]);
    }

    public function getStore() {
        $store = StoreSetting::first();
        return response()->json(['success' => true, 'data' => $store]);
    }

    public function updateStore(Request $request) {
        $store = StoreSetting::first();
        if (!$store) $store = StoreSetting::create($request->all());
        else $store->update($request->all());
        return response()->json(['success' => true, 'message' => 'Store details updated successfully', 'data' => $store]);
    }

    public function getAdminUsers() {
        return response()->json(['success' => true, 'data' => Admin::all()]);
    }

    public function createAdminUser(Request $request) {
        $admin = Admin::create([
            'username' => $request->username,
            'password' => bcrypt($request->password),
        ]);
        return response()->json(['success' => true, 'message' => 'Admin user created successfully', 'data' => $admin]);
    }

    public function updateAdminCredentials(Request $request) {
        $admin = Admin::first();
        if (!Hash::check($request->currentPassword, $admin->password)) {
            return response()->json(['success' => false, 'message' => 'Current password incorrect'], 403);
        }
        if ($request->newUsername) $admin->username = $request->newUsername;
        if ($request->newPassword) {
            if ($request->newPassword != $request->confirmPassword) {
                return response()->json(['success' => false, 'message' => 'Password confirmation mismatch'], 422);
            }
            $admin->password = bcrypt($request->newPassword);
        }
        $admin->save();
        return response()->json(['success' => true, 'message' => 'Credentials updated successfully']);
    }

    public function deleteAdminUser($id) {
        Admin::destroy($id);
        return response()->json(['success' => true, 'message' => 'Admin user deleted successfully']);
    }

    public function getCustomers(Request $request) {
        $customers = Customer::paginate($request->per_page ?? 10);
        return response()->json(['success' => true, 'data' => $customers->items(), 'pagination' => [
            'current_page' => $customers->currentPage(),
            'total_pages' => $customers->lastPage(),
            'total_items' => $customers->total(),
            'per_page' => $customers->perPage()
        ]]);
    }

    public function searchCustomers(Request $request) {
        $term = $request->q;
        $data = Customer::where('full_name', 'like', "%$term%")
                        ->orWhere('phone_number', 'like', "%$term%")
                        ->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getCustomerById($id) {
        $customer = Customer::with('transactions')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $customer]);
    }

    public function createCustomer(Request $request) {
        $customer = Customer::create([
            'full_name' => $request->fullName,
            'phone_number' => $request->phoneNumber,
            'email' => $request->email,
            'gender' => $request->gender,
            'pin_code' => $request->pinCode,
        ]);
        return response()->json(['success' => true, 'message' => 'Customer registered successfully', 'data' => $customer]);
    }

    public function updateCustomer(Request $request, $id) {
        $customer = Customer::findOrFail($id);
        $customer->update($request->all());
        return response()->json(['success' => true, 'message' => 'Customer updated successfully', 'data' => $customer]);
    }

    public function deleteCustomer($id) {
        Customer::destroy($id);
        return response()->json(['success' => true, 'message' => 'Customer deleted successfully']);
    }

    public function earnPoints(Request $request) {
        $customer = Customer::findOrFail($request->customerId);
        $transaction = $customer->transactions()->create([
            'type' => 'earned',
            'points' => $request->points,
            'amount' => $request->amount,
            'description' => $request->description
        ]);
        $customer->points += $request->points;
        $customer->save();
        return response()->json(['success' => true, 'message' => 'Points added successfully', 'data' => [
            'transaction' => $transaction,
            'customer' => $customer
        ]]);
    }

    public function redeemPoints(Request $request) {
        $customer = Customer::findOrFail($request->customerId);
        $transaction = $customer->transactions()->create([
            'type' => 'redeemed',
            'points' => -$request->points,
            'amount' => $request->cashValue,
            'description' => $request->description
        ]);
        $customer->points -= $request->points;
        $customer->save();
        return response()->json(['success' => true, 'message' => 'Points redeemed successfully', 'data' => compact('transaction', 'customer')]);
    }

    public function deductPoints(Request $request) {
        $customer = Customer::findOrFail($request->customerId);
        $pointsToDeduct = floor($request->refundAmount * 2);
        $transaction = $customer->transactions()->create([
            'type' => 'deducted',
            'points' => -$pointsToDeduct,
            'amount' => $request->refundAmount,
            'reason' => $request->reason,
        ]);
        $customer->points -= $pointsToDeduct;
        $customer->save();
        return response()->json(['success' => true, 'message' => 'Points deducted successfully', 'data' => compact('transaction', 'customer', 'pointsToDeduct')]);
    }

    public function getTransactions(Request $request) {
        $query = Transaction::with('customer');
        if ($request->type) $query->where('type', $request->type);
        if ($request->customer_id) $query->where('customer_id', $request->customer_id);
        $data = $query->paginate($request->per_page ?? 10);
        return response()->json(['success' => true, 'data' => $data->items(), 'pagination' => [
            'current_page' => $data->currentPage(),
            'total_pages' => $data->lastPage(),
            'total_items' => $data->total()
        ]]);
    }

    public function deleteTransaction($id) {
        Transaction::destroy($id);
        return response()->json(['success' => true, 'message' => 'Transaction deleted successfully']);
    }

    public function getCampaigns() {
        return response()->json(['success' => true, 'data' => Campaign::all()]);
    }

    public function createCampaign(Request $request) {
        $campaign = Campaign::create($request->all());
        return response()->json(['success' => true, 'message' => 'Campaign created successfully', 'data' => $campaign]);
    }

    public function updateCampaign(Request $request, $id) {
        $campaign = Campaign::findOrFail($id);
        $campaign->update($request->all());
        return response()->json(['success' => true, 'message' => 'Campaign updated successfully', 'data' => $campaign]);
    }

    public function deleteCampaign($id) {
        Campaign::destroy($id);
        return response()->json(['success' => true, 'message' => 'Campaign deleted successfully']);
    }

    public function customerRegister(Request $request) {
        $customer = Customer::create([
            'full_name' => $request->fullName,
            'phone_number' => $request->phoneNumber,
            'email' => $request->email,
            'gender' => $request->gender,
            'pin_code' => $request->pinCode,
        ]);
        return response()->json(['success' => true, 'message' => 'Registration successful', 'data' => $customer]);
    }

    public function customerLogin(Request $request) {
        $customer = Customer::where('phone_number', $request->phoneNumber)->first();
        if (!$customer || $customer->pin_code !== $request->pinCode) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }
        return response()->json(['success' => true, 'data' => [
            'token' => 'demo-customer-token',
            'customer' => $customer
        ]]);
    }

    public function getCustomerProfile() {
        $customer = Customer::with('transactions')->first();
        return response()->json(['success' => true, 'data' => [
            'customer' => $customer,
            'transactions' => $customer->transactions
        ]]);
    }

    public function redeemRequest(Request $request) {
        $value = $request->points * 0.05;
        return response()->json(['success' => true, 'message' => 'Redemption request submitted successfully', 'data' => [
            'requestId' => rand(100, 999),
            'points' => $request->points,
            'cashValue' => "$" . number_format($value, 2),
            'status' => 'pending',
            'submittedAt' => now()
        ]]);
    }
}
