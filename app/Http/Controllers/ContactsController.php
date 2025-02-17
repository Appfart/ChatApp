<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contact;

class ContactsController extends Controller
{
    // Display all contacts
    public function index()
    {
        $contacts = Contact::all(); // Fetch all contacts
        return view('pages.app.contacts', [
            'title' => 'Contacts',
            'contacts' => $contacts
        ]);
    }

    // Store a new contact
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:contacts,email',
            'occupation' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
            'location' => 'nullable|string|max:255'
        ]);

        Contact::create($request->all());

        return redirect()->route('contacts.index')->with('success', 'Contact added successfully.');
    }

    // Update an existing contact
    public function update(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:contacts,email,$id",
            'occupation' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:15',
            'location' => 'nullable|string|max:255'
        ]);

        $contact->update($request->all());

        return redirect()->route('contacts.index')->with('success', 'Contact updated successfully.');
    }

    // Delete a contact
    public function destroy($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact deleted successfully.');
    }
}
