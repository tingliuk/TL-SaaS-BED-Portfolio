<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJokeRequest;
use App\Http\Requests\UpdateJokeRequest;
use App\Models\Joke;

class JokeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJokeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Joke $joke)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Joke $joke)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJokeRequest $request, Joke $joke)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Joke $joke)
    {
        //
    }
}
