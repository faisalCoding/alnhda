<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LawhatiMemberResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * What لوحتي, the team's Kanban app, reads with an employee's sign-in code:
 * who is signed in, and the team, so cards can be assigned to people. It is
 * read-only, and a sign-in code reaches nothing else.
 */
class LawhatiController extends Controller
{
    public function me(Request $request): LawhatiMemberResource
    {
        $employee = $request->user();

        if (! $employee instanceof Employee) {
            throw new AccessDeniedHttpException('This token does not belong to an employee.');
        }

        return new LawhatiMemberResource($employee);
    }

    /**
     * Everyone, including employees who left, so cards assigned to them
     * still show who they were. The app offers only the active ones.
     *
     * @return AnonymousResourceCollection<int, LawhatiMemberResource>
     */
    public function team(): AnonymousResourceCollection
    {
        return LawhatiMemberResource::collection(
            Employee::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }
}
