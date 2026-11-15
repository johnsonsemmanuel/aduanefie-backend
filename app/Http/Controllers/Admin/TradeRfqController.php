<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeRfqController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('trade_rfqs')->orderByDesc('id');
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $rfqs = $query->get();
        $quotesByRfq = DB::table('trade_rfq_quotes')
            ->whereIn('trade_rfq_id', $rfqs->pluck('id'))
            ->orderBy('quoted_unit_price_ghs')
            ->get()
            ->groupBy('trade_rfq_id');

        return view('admin-views.trade-rfqs.index', compact('rfqs', 'quotesByRfq'));
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:open,quoted,awarded,closed',
        ]);

        DB::table('trade_rfqs')->where('id', $id)->update([
            'status' => $data['status'],
            'updated_at' => now(),
        ]);

        Toastr::success('RFQ status updated.');
        return back();
    }

    public function acceptQuote($quoteId)
    {
        $quote = DB::table('trade_rfq_quotes')->where('id', $quoteId)->first();
        if (! $quote) {
            Toastr::error('Quote not found.');
            return back();
        }

        DB::table('trade_rfq_quotes')->where('trade_rfq_id', $quote->trade_rfq_id)->update([
            'status' => 'rejected',
            'updated_at' => now(),
        ]);

        DB::table('trade_rfq_quotes')->where('id', $quoteId)->update([
            'status' => 'accepted',
            'updated_at' => now(),
        ]);

        DB::table('trade_rfqs')->where('id', $quote->trade_rfq_id)->update([
            'status' => 'awarded',
            'updated_at' => now(),
        ]);

        Toastr::success('Winning supplier quote accepted and RFQ marked awarded.');
        return back();
    }

    public function destroy($id)
    {
        DB::table('trade_rfq_quotes')->where('trade_rfq_id', $id)->delete();
        DB::table('trade_rfqs')->where('id', $id)->delete();
        Toastr::success(translate('messages.deleted_successfully'));
        return back();
    }
}
