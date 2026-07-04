<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\VisitorResource;
use App\Models\Visitor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VisitorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return VisitorResource::collection(
            Visitor::query()->latest()->get()
        );
    }
}
