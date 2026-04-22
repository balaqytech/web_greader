<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BotContactResource;
use App\Models\BotContact;
use Illuminate\Http\Request;

class BotContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contacts = BotContact::query()
            ->latest()
            ->paginate(10);

        return BotContactResource::collection($contacts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'channel' => 'required|string',
            'sender_name' => 'nullable|string',
            'whatsapp' => 'required|string|unique:bot_contacts,whatsapp',
            'notes' => 'nullable|string',
            'additional_data' => 'nullable|array',
        ]);

        try {
            $contact = BotContact::create($data);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            return response()->json([
                'error' => 422,
                'message' => 'This whatsapp number is already exists',
            ], 422);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => 500,
                'message' => $exception->getMessage(),
            ], 500);
        }

        return new BotContactResource($contact);
    }

    /**
     * Display the specified resource.
     */
    public function show(BotContact $botContact)
    {
        return new BotContactResource($botContact);
    }
}
