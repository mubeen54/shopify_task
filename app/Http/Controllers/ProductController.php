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
                fileSize: "{$file->getSize()}",
                httpMethod: POST
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

            Log::info('Staged upload response:', ['response' => $responseStagedUpload]);

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

            Log::info('Upload response:', ['response' => $responseUpload->getBody()]);

            if ($responseUpload->getStatusCode() !== 201) {
                return response()->json(['message' => 'Failed to upload file to staged URL'], 500);
            }

            // Extract the temporary URL from the upload response
            $uploadResponseXml = simplexml_load_string($responseUpload->getBody());
            $tempUrl = (string) $uploadResponseXml->Location;

            // Step 3: Call fileCreate mutation
            $mutationFileCreate = <<<GRAPHQL
            mutation fileCreate(\$files: [FileCreateInput!]!) {
              fileCreate(files: \$files) {
                files {
                  fileStatus
                  ... on MediaImage {
                    id
                  }
                }
                userErrors {
                  field
                  message
                }
              }
            }
            GRAPHQL;

            $fileInput = [
                [
                    'alt' => 'Uploaded image',
                    'contentType' => 'IMAGE',
                    'originalSource' => $tempUrl,
                ],
            ];

            $responseFileCreate = $shop->api()->graph($mutationFileCreate, ['files' => $fileInput]);

            Log::info('File create response:', ['response' => $responseFileCreate]);

            if ($responseFileCreate['body']['data']['fileCreate']['files'][0]['fileStatus'] != 'UPLOADED') {
                return response()->json([
                    'message' => 'Failed to create file in Shopify',
                    'errors' => $responseFileCreate['body']['data']['fileCreate']['userErrors'],
                ], 500);
            }

            return response()->json(['imageUrl' => $tempUrl]);
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
            'image' => 'required|string',
        ]);

        try {
            // Step 1: Create the product
            $mutationCreateProduct = <<<GRAPHQL
            mutation productCreate(\$input: ProductInput!) {
              productCreate(input: \$input) {
                product {
                  id
                  title
                  createdAt
                }
                userErrors {
                  field
                  message
                }
              }
            }
            GRAPHQL;

            $productInput = [
                'title' => $data['title'],
            ];

            $responseCreateProduct = $shop->api()->graph($mutationCreateProduct, [
                'input' => $productInput,
            ]);

            Log::info('Product creation response:', ['response' => $responseCreateProduct]);

            // Validate response structure
            $productCreateData = $responseCreateProduct['body']['data']['productCreate'] ?? null;

            $productId = $productCreateData['product']['id'];

            // Step 2: Attach the media (image) to the product
            $mutationAttachMedia = <<<GRAPHQL
            mutation productMediaCreate(\$media: [CreateMediaInput!]!) {
              productCreateMedia(productId: "$productId", media: \$media) {
                media {
                  mediaContentType
                  alt
                  status
                }
                userErrors {
                  field
                  message
                }
              }
            }
            GRAPHQL;

            $mediaInput = [
                [
                    'alt' => 'Uploaded image',
                    'mediaContentType' => 'IMAGE',
                    'originalSource' => $data['image'],
                ],
            ];

            Log::info('Attaching media to product:', ['mediaInput' => $mediaInput]);

            $responseAttachMedia = $shop->api()->graph($mutationAttachMedia, [
                'media' => $mediaInput,
            ]);

            Log::info('Attach media response:', ['response' => $responseAttachMedia]);

    

            return response()->json([
                'id' => $productId,
                'title' => $data['title'],
                'image' => $data['image'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating product:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create product', 'error' => $e->getMessage()], 500);
        }
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

    //     return response()->json(['message' => 'Product updated successfully']);
    // }}
