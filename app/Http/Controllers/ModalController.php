<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class ModalController extends Controller
{
    /**
     * Show the sample modal
     */
    public function sample(Request $request)
    {
        return Inertia::modal('SampleModal', [
            'message' => $request->get('message', 'This is a sample modal to demonstrate the inertiaui/modal package!')
        ])->baseUrl(route('dashboard'));
    }

    /**
     * Handle the sample modal form submission
     */
    public function storeSample(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:1000',
        ]);

        // Here you would normally save the data or perform some action
        // For now, we'll just return a success message

        return redirect()->route('dashboard')->with('success', 'Sample modal form submitted successfully!');
    }
}
