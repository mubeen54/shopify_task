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

    public function uploadImage(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $file = $request->file('image');
            if (!$file) {
                return response()->json(['message' => 'No file received'], 400);
            }

            $shop = Auth::user();

            // Step 1: Create staged upload
            $mutationStagedUpload = <<<GRAPHQL
            mutation {
              stagedUploadsCreate(input: {
                resource: PRODUCT_IMAGE,
                filename: "{$file->getClientOriginalName()}",
                mimeType: "{$file->getMimeType()}",
                fileSize: "{$file->getSize()}"
              }) {
                stagedTargets {
                  url
                  parameters {
                    name
                    value
                  }
                }
              }
            }
            GRAPHQL;

            $responseStagedUpload = $shop->api()->graph($mutationStagedUpload);

            if (!empty($responseStagedUpload['body']['errors'])) {
                return response()->json(['message' => 'Failed to create staged upload'], 500);
            }

            $stagedTarget = $responseStagedUpload['body']['data']['stagedUploadsCreate']['stagedTargets'][0];
            $uploadUrl = $stagedTarget['url'];
            $parameters = $stagedTarget['parameters'];

            // Step 2: Use GuzzleHttp\Client to upload the file
            $client = new \GuzzleHttp\Client();
            $multipart = [];
            foreach ($parameters as $parameter) {
                $multipart[] = [
                    'name' => $parameter['name'],
                    'contents' => $parameter['value'],
                ];
            }
            $multipart[] = [
                'name' => 'file',
                'contents' => fopen($file->getRealPath(), 'r'),
            ];

            $responseUpload = $client->post($uploadUrl, [
                'multipart' => $multipart,
            ]);

            if ($responseUpload->getStatusCode() !== 200) {
                return response()->json(['message' => 'Failed to upload file to staged URL'], 500);
            }

            // Step 3: Return the uploaded image URL
            return response()->json(['imageUrl' => $parameters[0]['value']]);
        } catch (\Exception $e) {
            Log::error('Error uploading image:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to upload image to Shopify', 'error' => $e->getMessage()], 500);
        }
    }

    public function storeProduct(Request $request)
    {
        $shop = Auth::user();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'count' => 'required|integer|min:1',
            'image' => 'required|string',
        ]);

        $mutation = <<<GRAPHQL
        mutation {
          productCreate(input: {
            title: "{$data['title']}",
            variants: [
              {
                inventoryQuantity: {$data['count']}
              }
            ],
            images: [
              {
                src: "{$data['image']}"
              }
            ]
          }) {
            product {
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
            userErrors {
              field
              message
            }
          }
        }
        GRAPHQL;

        $response = $shop->api()->graph($mutation);

        if (!empty($response['body']['data']['productCreate']['userErrors'])) {
            return response()->json(['message' => 'Failed to create product'], 500);
        }

        $product = $response['body']['data']['productCreate']['product'];

        return response()->json([
            'id' => $product['id'],
            'title' => $product['title'],
            'image' => [
                'src' => $product['images']['edges'][0]['node']['src'] ?? null,
            ],
        ]);
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
}

    // public function update(Request $request, $id)
    // {
    //     $shop = Auth::user();
    //     $data = $request->only('title', 'image');

    //     $response = $shop->api()->rest('PUT', "/admin/api/2023-01/products/{$id}.json", [
    //         'product' => [
    //             'id' => (int)$id,
    //             'title' => $data['title'],
    //             'image' => isset($data['image']) ? [['src' => $data['image']]] : [], // Update image if provided
    //         ]
    //     ]);

    //     if ($response['errors']) {
    //         return response()->json(['message' => 'Failed to update product'], 500);
    //     }

    //     return response()->json(['message' => 'Product updated successfully']);
    // }}
