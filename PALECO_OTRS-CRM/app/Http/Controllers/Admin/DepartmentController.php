<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Department;

class DepartmentController extends Controller
{
public function index()
{
    $departments = Department::query();

    if ($searchTerm = request('search')) {
        $departments = $departments->search($searchTerm);
    }

    if ($sortOption = request('sort')) {
        $departments = $departments->sort($sortOption);
    }

    $departments = $departments->paginate(9)
                               ->withQueryString();

    return view('admin.pages.departmentManagement', compact('departments'));
}


    public function createForm()
    {
        // Logic to show a form for creating a new department
    }
}
