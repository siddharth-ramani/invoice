<?php

namespace App\Http\Controllers;

use App\Models\GstSlab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GstSlabController extends Controller
{
    public function index()
    {
        $vendor = Auth::user();

        $slabs = GstSlab::where('user_id', $vendor->id)->get();

        return view('gst.index', compact('slabs'));
    }

    public function create()
    {
        return view('gst.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        GstSlab::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'rate' => $request->rate,
        ]);

        return redirect()->route('gst.index')->with('success', 'GST slab added successfully');
    }

    public function edit(GstSlab $gst)
    {
        if ($gst->user_id !== Auth::id()) {
            abort(403);
        }

        return view('gst.edit', compact('gst'));
    }

    public function update(Request $request, GstSlab $gst)
    {
        if ($gst->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'rate' => 'required|numeric|min:0|max:100',
        ]);

        $gst->update([
            'name' => $request->name,
            'rate' => $request->rate,
        ]);

        return redirect()->route('gst.index')->with('success', 'GST slab updated successfully');
    }

    public function destroy(GstSlab $gst)
    {
        if ($gst->user_id !== Auth::id()) {
            abort(403);
        }

        $gst->delete();

        return redirect()->route('gst.index')->with('success', 'GST slab deleted successfully');
    }
}
