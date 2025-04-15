<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductRequest;
use App\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(private readonly ResponseFactory $responseFactory) {}
    public function store(CreateProductRequest $request)
    {
        $data = $request->validated();
        $count = $data['count'];

        /** @var ShopModel $shop */
        $shop = $request->user();
        $productResource = [
            "title" => "Good Product",
            "body_html" => "<strong>Good snowboard!</strong>"
        ];

        $request = $shop->api()->rest(
            'POST',
            '/admin/api/products.json',
            [
                'product' => $productResource
            ]
        );
        return $this->responseFactory->json($request['body']['product']);
    }

    public function index(Request $request)
    {
        $shop = Auth::user();
        $queryText = $request->query('query', '');

        $query = <<<GRAPHQL
    {
      shop {
        name
        products(first: 50, query: "$queryText") {
          edges {
            node {
              id
              title
              images(first: 1) {
                edges {
                  node {
                    src
                  }
                }
              }
            }
          }
        }
      }
    }
    GRAPHQL;

        $response = $shop->api()->graph($query);

        $products = collect($response['body']['data']['shop']['products']['edges'])->map(function ($edge) {
            return [
                'id' => $edge['node']['id'],
                'title' => $edge['node']['title'],
                'image' => [
                    'src' => $edge['node']['images']['edges'][0]['node']['src'] ?? null
                ]
            ];
        });

        return response()->json($products);
    }




    public function destroy($id)
    {
        $shop = Auth::user();
        Log::info('Deleting product with ID: ' . $id);

        $response = $shop->api()->rest('DELETE', "/admin/api/2023-01/products/{$id}.json");

        if ($response['errors']) {
            return response()->json(['message' => 'Failed to delete product'], 500);
        }

        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function update(Request $request, $id)
    {
        $shop = Auth::user();
        $data = $request->only('title', 'image');

        $response = $shop->api()->rest('PUT', "/admin/api/2023-01/products/{$id}.json", [
            'product' => [
                'id' => (int)$id,
                'title' => $data['title'],
                'image' => isset($data['image']) ? [['src' => $data['image']]] : [], // Update image if provided
            ]
        ]);

        if ($response['errors']) {
            return response()->json(['message' => 'Failed to update product'], 500);
        }

        return response()->json(['message' => 'Product updated successfully']);
    }

    public function uploadImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $path = $request->file('image')->store('product-images', 'public');

            return response()->json(['imageUrl' => Storage::url($path)]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to upload image', 'error' => $e->getMessage()], 500);
        }
    }
}
