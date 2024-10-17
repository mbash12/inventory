<?php

namespace Src\Controllers;

use App\Http\Controllers\Controller;
use Src\Models\Project;
use Src\Models\Shipper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;
use Src\Models\Product;

class MarketingController extends Controller
{

    public function __construct()
    {
        $this->middleware('jwt.verify');
    }
    public function createProjects(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_number' => 'required|unique:projects',
            'client_po_number' => 'string',
            'submit_date' => 'required|date',
            'pic_name' => 'string',
            'pic_phone' => 'string',
            'client_company' => 'string',
            'client_pic_name' => 'string',
            'client_pic_phone' => 'string',
            'shipper_id' => 'required|exists:shippers,id',

            'products.*.name' => 'required',
            'products.*.description' => 'string',
            'products.*.quantity' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->createErrorResponse($validator->errors(), 400);
        }

        $products = [];
        foreach ($request->products as $productData) {
            $product = new Product($productData);
            $products[] = $product;
        }

        $projects = Project::create([
            "job_number" => $request->job_number,
            "submit_date" => $request->submit_date,
            "pic_name" => $request->pic_name,
            "pic_phone" => $request->pic_phone,
            "client_company" => $request->client_company,
            "client_pic_name" => $request->client_pic_name,
            "client_pic_phone" => $request->client_pic_phone,
            "client_po_number" => $request->client_po_number,
            "shipper_id" => $request->shipper_id,
        ]);
        
        $projects->products()->saveMany($products);

        return $this->createResponse($projects, 'Project created successfully', 'Failed to create Project');
    }

    public function getProjectList(Request $request)
    {
        $query = Project::query();

        if ($request->has('statuses')) {
            $statuses = explode(',', $request->input('statuses'));
            $query->whereIn('status', $statuses);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            if ($startDate == $endDate) {
                $query->whereDate('submit_date', $startDate);
            } else {
                $query->whereBetween('submit_date', [$startDate, $endDate]);
            }
        }

        if ($request->has('po_status')) {
            $po_status = $request->input('po_status');
            if ($po_status) {
                $query->whereNotNull('client_po_number');
            } else {
                $query->whereNull('client_po_number');
            }
        }

        $totalRecords = $query->count();
        $limit = $request->input('limit', 10);
        $currentPage = $request->input('page', 1);
        $offset = ($currentPage - 1) * $limit;
        $query->offset($offset)->limit($limit);
        $results = $query->get();

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'total_records' => $totalRecords,
                'total_pagess' => ceil($totalRecords / $limit),
                'current_page' => $currentPage,
                'limit_per_page' => $limit,
            ]
        ]);
    }

    public function getSingleProject(Request $request)
    {
        $projectId = $request->route('id');
        $project = Project::find($projectId);

        return $this->createResponseWithData($project, 'Project not found');
    }

    public function updateProject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_number' => 'required',
            'client_po_number' => 'string',
            'submit_date' => 'required|date',
            'pic_name' => 'string',
            'pic_phone' => 'string',
            'client_company' => 'string',
            'client_pic_name' => 'string',
            'client_pic_phone' => 'string',
            'shipper_id' => 'required|exists:shippers,id',

            'products.*.name' => 'required',
            'products.*.description' => 'string',
            'products.*.quantity' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->createErrorResponse($validator->errors(), 400);
        }

        $projectId = $request->route('id');
        $project = Project::findOrFail($projectId);

        $project->update([
            "job_number" => $request->job_number,
            "submit_date" => $request->submit_date,
            "pic_name" => $request->pic_name,
            "pic_phone" => $request->pic_phone,
            "client_company" => $request->client_company,
            "client_pic_name" => $request->client_pic_name,
            "client_pic_phone" => $request->client_pic_phone,
            "client_po_number" => $request->client_po_number,
            "shipper_id" => $request->shipper_id,
        ]);

        $projectProductIds = $project->products->pluck('id')->toArray();

        foreach ($request->products as $productData) {
            if (isset($productData['id'])) {
                $productId = $productData['id'];
                if (in_array($productId, $projectProductIds)) {
                    $product = Product::findOrFail($productId);
                    $product->update($productData);
                }
            } else {
                $project->products()->create($productData);
            }
        }

        $productsToDelete = array_diff($projectProductIds, array_column($request->products, 'id'));
        $project->products()->whereIn('id', $productsToDelete)->delete();

        return $this->createResponse($project, 'Project updated successfully', 'Failed to update Project');
    }

    public function deleteProject(Request $request)
    {
        $projectId = $request->route('id');
        $project = Project::findOrFail($projectId);
        $project->delete();

        return response()->json(['success' => true, 'message' => 'Project deleted successfully'], 200);
    }

    private function createResponse($data, $successMessage, $failureMessage)
    {
        if ($data) {
            return response()->json(['success' => true, 'message' => $successMessage], 201);
        } else {
            return response()->json(['success' => false, 'message' => $failureMessage], 500);
        }
    }

    private function createResponseWithData($data, $errorMessage)
    {
        if ($data) {
            return response()->json(['success' => true, 'data' => $data], 200);
        } else {
            return response()->json(['success' => false, 'message' => $errorMessage], 404);
        }
    }

    private function createErrorResponse($errors, $statusCode)
    {
        return response()->json(['success' => false, 'errors' => $errors], $statusCode);
    }
}
