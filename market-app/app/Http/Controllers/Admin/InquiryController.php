<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function index(): View
    {
        return view('admin.inquiries.index', [
            'inquiries' => Inquiry::query()->with('item')->latest()->get(),
        ]);
    }

    public function update(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,done,canceled'],
        ]);

        $inquiry->update($data);

        return back()->with('status', 'Статус заявки обновлен.');
    }
}
