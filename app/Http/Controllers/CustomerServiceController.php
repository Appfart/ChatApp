<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Events\NewTicketCreated;
use App\Events\NewTicketMessage;
use App\Events\TicketClosed;

class CustomerServiceController extends Controller
{
    // Display list of tickets (for support/admin users)
    public function index()
    {
        $userId = Auth::id();
        $userRole = Auth::user()->role;
    
        Log::info('User accessing ticket index', ['user_id' => $userId, 'role' => $userRole]);
    
        if ($userRole == 'user') {
            $tickets = Ticket::where('user_id', $userId)
                //->where('status', '!=', 'closed')
                ->with(['latestMessage.user'])
                ->orderBy('updated_at', 'desc') // Sort by latest updated first
                ->get();
        } else {
            $tickets = Ticket::where('status', '!=', 'closed_none')
                ->with(['latestMessage.user'])
                ->orderBy('updated_at', 'desc') // Sort by latest updated first
                ->get();
        }
    
        return view('pages.app.tickets.index', compact('tickets'));
    }

    // Show form to create a new ticket
    public function create()
    {
        $userId = Auth::id();

        Log::info('User accessing ticket creation form', ['user_id' => $userId]);

        return view('pages.app.tickets.create');
    }

    // Store a new ticket
    public function store(Request $request)
    {
        $userId = Auth::id();

        Log::info('User attempting to store a new ticket', ['user_id' => $userId]);

        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $ticket = Ticket::create([
            'user_id' => $userId,
            'subject' => $request->subject,
            'status' => 'open',
        ]);

        // Add the initial message
        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $request->message,
        ]);

        Log::info('New ticket created', ['ticket_id' => $ticket->id, 'user_id' => $userId]);

        // Fire the NewTicketCreated event
        event(new NewTicketCreated($ticket));

        return redirect()->route('tickets.show', $ticket->id)->with('success', 'Ticket created successfully.');
    }

    // Display a specific ticket and its messages
    public function show($id)
    {
        $userId = Auth::id();
        $userRole = Auth::user()->role;
    
        Log::info('User attempting to view ticket', ['user_id' => $userId, 'ticket_id' => $id]);
    
        // Retrieve the ticket with related messages and users
        $ticket = Ticket::with('messages.user')->findOrFail($id);
    
        // Check if the user is authorized to view the ticket
        if ($userRole === 'user' && $ticket->user_id !== $userId) {
            Log::warning('Unauthorized access attempt to ticket', ['user_id' => $userId, 'ticket_id' => $id]);
            abort(403, 'Unauthorized action.');
        }
    
        // Update the ticket status to 'open' if it's not already open and not closed
        if ($ticket->status !== 'open' && $ticket->status !== 'closed') {
            $ticket->status = 'open';
            $ticket->save();
    
            Log::info('Ticket status updated to open', ['ticket_id' => $id, 'updated_by' => $userId]);
        } else {
            if ($ticket->status === 'closed') {
                Log::info('Ticket is closed; status remains unchanged', ['ticket_id' => $id, 'user_id' => $userId]);
            } else {
                Log::info('Ticket status is already open', ['ticket_id' => $id, 'user_id' => $userId]);
            }
        }
    
        return view('pages.app.tickets.show', compact('ticket'));
    }

    // Store a new message in a ticket
    public function storeMessage(Request $request, $id)
    {
        $userId = Auth::id();
        $userRole = Auth::user()->role;
    
        Log::info('User attempting to add a message to the ticket', ['user_id' => $userId, 'role' => $id, 'ticket_id' => $id]);
    
        $ticket = Ticket::findOrFail($id);
    
        // Check if the ticket is closed
        if ($ticket->status === 'closed') {
            Log::warning('Attempted to add a message to a closed ticket', ['user_id' => $userId, 'ticket_id' => $id]);
            return response()->json(['message' => 'Cannot add messages to a closed ticket.'], 403);
        }
    
        // Check if the user has permission to add a message
        if ($userRole === 'user' && $ticket->user_id !== $userId) {
            Log::warning('Unauthorized attempt to add a message to the ticket', ['user_id' => $userId, 'ticket_id' => $id]);
            abort(403, 'Unauthorized action.');
        }
    
        // Validate the request, including the image
        $request->validate([
            'message' => 'nullable|string|required_without:image',
            'image' => 'required_without:message|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

    
        $data = [
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $request->message,
        ];
    
        // Handle image upload if present
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('tickets/messages', 'public'); // Store in 'public/tickets/messages'
            $data['image_url'] = Storage::url($path); // Get the URL to store
        }
    
        $message = TicketMessage::create($data);
    
        Log::info('New message added to the ticket', [
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message_id' => $message->id,
        ]);
    
        // Optional: Update ticket status
        if ($userRole !== 'user') {
            $ticket->status = 'pending';
            $ticket->save();
            Log::info('Ticket status updated to pending', ['ticket_id' => $ticket->id]);
        }
    
        // Trigger event
        event(new NewTicketMessage($message));
        Log::info('Event NewTicketMessage triggered', ['ticket_id' => $message->ticket_id, 'message_id' => $message->id]);
    
        // Return JSON response
        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => $message->load('user'), // Load user relationship
        ], 201);
    }

    // Close a ticket
    public function close($id)
    {
        $userId = Auth::id();
        $userRole = Auth::user()->role;

        Log::info('User attempting to close ticket', ['user_id' => $userId, 'ticket_id' => $id]);

        $ticket = Ticket::findOrFail($id);

        // Only support/admin can close tickets
        if ($userRole == 'user') {
            Log::warning('Unauthorized ticket close attempt', ['user_id' => $userId, 'ticket_id' => $id]);
            abort(403);
        }

        $ticket->status = 'closed';
        $ticket->save();

        Log::info('Ticket closed', ['ticket_id' => $ticket->id, 'closed_by' => $userId]);

        // Fire the TicketClosed event
        event(new TicketClosed($ticket));

        return response()->json([
            'message' => 'Ticket closed successfully.',
            'ticket' => $ticket,
        ], 200);
    }

    // API: Get list of tickets for the authenticated user
    public function apiIndex()
    {
        $user = Auth::user();
    
        if (in_array($user->role, ['support', 'admin', 'superadmin'])) {
            // Support/admin users see all tickets
            $tickets = Ticket::with(['user', 'latestMessage.user'])->orderBy('updated_at', 'desc')->get();
        } else {
            // Regular users see only their tickets
            $tickets = Ticket::with(['user', 'latestMessage.user'])->where('user_id', $user->id)->orderBy('updated_at', 'desc')->get();
        }
    
        // Add translated status labels
        $tickets->transform(function ($ticket) {
            $statusLabels = [
                'pending' => '未读',   // Pending
                'open' => '已读',      // Open
                'closed' => '关闭'     // Closed
            ];
    
            $ticket->status_label = $statusLabels[$ticket->status] ?? $ticket->status;
            return $ticket;
        });
    
        return response()->json($tickets);
    }

    public function apiStore(Request $request)
    {
        $userId = Auth::id();
    
        Log::info('API request to create a new ticket', ['user_id' => $userId]);
    
        // Check if the user already has an open or pending ticket
        $existingTicket = Ticket::where('user_id', $userId)
                                ->whereIn('status', ['open', 'pending']) // Adjust statuses here
                                ->first();
    
        if ($existingTicket) {
            Log::warning('User attempted to create a new ticket while having unresolved tickets', [
                'user_id' => $userId, 
                'existing_ticket_id' => $existingTicket->id, 
                'existing_ticket_status' => $existingTicket->status,
            ]);
    
            return response()->json([
                'message' => 'You already have an unresolved ticket.',
                'ticket' => $existingTicket,
            ], 400); // 400 Bad Request or another appropriate status code
        }
    
        // Validate the incoming request data
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);
    
        // Create the new ticket
        $ticket = Ticket::create([
            'user_id' => $userId,
            'subject' => $request->subject,
            'status' => 'open',
        ]);
    
        // Add the initial message to the ticket
        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $request->message,
        ]);
    
        // Trigger the event for a new ticket creation
        event(new NewTicketCreated($ticket));
    
        Log::info('API ticket created', ['ticket_id' => $ticket->id, 'user_id' => $userId]);
    
        return response()->json([
            'message' => 'Ticket created successfully.',
            'ticket' => $ticket,
        ], 201);
    }

    // API: Get a specific ticket and its messages
    public function apiShow($id)
    {
        $user = Auth::user();
        $userId = $user->id;
        $userRole = $user->role;
    
        Log::info('API请求查看票据', ['user_id' => $userId, 'ticket_id' => $id]);
    
        // 获取带有相关消息和用户的票据
        $ticket = Ticket::with('messages.user')->findOrFail($id);
    
        // 检查用户是否有权限查看票据
        if ($userRole === 'user' && $ticket->user_id !== $userId) {
            Log::warning('API未授权的用户尝试查看票据', ['user_id' => $userId, 'ticket_id' => $id]);
            return response()->json(['message' => '未授权。'], 403);
        }
    
        return response()->json($ticket);
    }

    // API: Add a message to a ticket
    public function apiStoreMessage(Request $request, $id)
    {
        $user = Auth::user();
        $userId = $user->id;
        $userRole = $user->role;
    
        Log::info('API request to add message to ticket', ['user_id' => $userId, 'ticket_id' => $id]);
    
        $ticket = Ticket::findOrFail($id);
    
        // Check if the ticket is closed
        if ($ticket->status === 'closed') {
            Log::warning('Attempt to add message to closed ticket', ['user_id' => $userId, 'ticket_id' => $id]);
            return response()->json(['message' => 'Cannot add message to a closed ticket.'], 403);
        }
    
        // Check if user is authorized to add a message
        if ($userRole === 'user' && $ticket->user_id !== $userId) {
            Log::warning('API unauthorized message attempt on ticket', ['user_id' => $userId, 'ticket_id' => $id]);
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
    
        // Validate input
        $request->validate([
            'message' => 'nullable|string|required_without:image',
            'image' => 'required_without:message|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

    
        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ticket_images', 'public');
        }
    
        // Create message
        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message' => $request->message,
            'image_url' => $imagePath ? asset('storage/' . $imagePath) : null, // Store full image URL
        ]);
    
        Log::info('API message added to ticket', [
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'message_id' => $message->id,
        ]);
    
        // Optionally update ticket status
        if ($userRole !== 'user') {
            $ticket->status = 'pending';
            $ticket->save();
            Log::info('API ticket status updated to pending', ['ticket_id' => $ticket->id]);
        }
    
        // Fire the NewTicketMessage event
        event(new NewTicketMessage($message));
    
        return response()->json([
            'message' => 'Message sent successfully.',
            'ticket_message' => $message,
        ], 201);
    }
}
