<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBotContactRequest;
use App\Http\Resources\BotContactResource;
use App\Models\BotContact;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

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
    public function store(StoreBotContactRequest $request)
    {
        $data = $request->validated();

        try {
            $contact = BotContact::create($data);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'error' => 'duplicate_whatsapp',
                'message' => __('alerts.api.bot_contact_duplicate'),
            ], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'error' => 'server_error',
                'message' => __('alerts.api.unexpected_error'),
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
