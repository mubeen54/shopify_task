<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

// class Controller extends BaseController
// {
//     use AuthorizesRequests, ValidatesRequests;
// }

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
        $query = $request->query('query', '');

        $endpoint = '/admin/api/2023-01/products.json';
        $params = [];

        if ($query) {
            $params['title'] = $query;
        }

        $products = $shop->api()->rest('GET', $endpoint, $params);

        return $this->responseFactory->json($products['body']['products']);
    }


    // public function destroy($id)
    // {
    //     $shop = Auth::user();

    //     $response = $shop->api()->rest('DELETE', "/admin/api/2023-01/products/{$id}.json");

    //     if ($response['errors']) {
    //         return response()->json(['message' => 'Failed to delete product'], 500);
    //     }

    //     return response()->json(['message' => 'Product deleted successfully']);
    // }

    public function update(Request $request, $id)
    {
        $shop = Auth::user();
        $data = $request->only('title', 'image');

        $productData = [];

        if (!empty($data['title'])) {
            $productData['title'] = $data['title'];
        }

        if (!empty($data['image'])) {
            $productData['images'] = [
                ['src' => $data['image']]
            ];
        }

        $response = $shop->api()->rest(
            'PUT',
            "/admin/api/2023-01/products/{$id}.json",
            ['product' => $productData]
        );

        if (isset($response['errors']) && $response['errors']) {
            return response()->json(['message' => 'Failed to update product'], 500);
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $response['body']['product'] ?? null
        ]);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif|max:2048',
            'productId' => 'required|string',
        ]);

        $shop = Auth::user();
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $fileMimeType = $file->getMimeType();
        $fileSize = $file->getSize();
        $productId = "gid://shopify/Product/8994490974373";

        $collectionsResponse = $shop->api()->rest('GET', '/admin/api/2023-01/custom_collections.json');
        $collections = $collectionsResponse['body']['custom_collections'] ?? [];

        if (empty($collections)) {
            return response()->json(['message' => 'No collections found'], 404);
        }

        // Use the first collection's ID
        $collectionId = "gid://shopify/Collection/" . $collections[0]['id'];
        Log::info('Using Collection ID: ' . $collectionId);

        // Step 1: Create staged upload
        $stagedUploadsQuery = <<<'GRAPHQL'
        mutation stagedUploadsCreate($input: [StagedUploadInput!]!) {
            stagedUploadsCreate(input: $input) {
                stagedTargets {
                    resourceUrl
                    url
                    parameters {
                        name
                        value
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $stagedUploadsVariables = [
            'input' => [
                [
                    'resource' => 'IMAGE',
                    'filename' => $fileName,
                    'mimeType' => $fileMimeType,
                    'fileSize' => (string) $fileSize, // Convert fileSize to string
                ],
            ],
        ];

        $stagedUploadsResponse = $shop->api()->graph($stagedUploadsQuery, $stagedUploadsVariables);
        Log::info('Staged Uploads Response', ['response' => $stagedUploadsResponse]);
        $stagedTarget = $stagedUploadsResponse['body']['data']['stagedUploadsCreate']['stagedTargets'][0] ?? null;

        if (!$stagedTarget) {
            return response()->json(['message' => 'Failed to create staged upload'], 500);
        }

        // Step 2: Upload file to the staged URL
        $formData = [];
        foreach ($stagedTarget['parameters'] as $param) {
            $formData[$param['name']] = $param['value'];
        }
        $formData['file'] = fopen($file->getRealPath(), 'r');

        $uploadResponse = Http::asMultipart()->post($stagedTarget['url'], $formData);


        Log::info('Upload Response uahan tak a rhah step 2');
        // if (!$uploadResponse->ok()) {
        //     return response()->json(['message' => 'Failed to upload file to staged URL'], 500);
        // }

        Log::info('Upload Response uahan tak a rhah step 3');

        // Step 3: Update collection with the uploaded image
        $collectionUpdateQuery = <<<'GRAPHQL'
        mutation collectionUpdate($input: CollectionInput!) {
            collectionUpdate(input: $input) {
                collection {
                    id
                    image {
                        originalSrc
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $collectionUpdateVariables = [
            'input' => [
                'id' => $collectionId,
                'image' => [
                    'src' => $stagedTarget['resourceUrl'],
                ],
            ],
        ];

        Log::info('Collection Update Variables', ['variables' => $collectionUpdateVariables]);

        $collectionUpdateResponse = $shop->api()->graph($collectionUpdateQuery, $collectionUpdateVariables);
        Log::info('----------------------------------------------------------------');
        Log::info('Collection Update Response', ['response' => $collectionUpdateResponse]);
        $updatedImage = $collectionUpdateResponse['body']['data']['collectionUpdate']['collection']['image']['originalSrc'] ?? null;

        // if (!$updatedImage) {
        //     return response()->json(['message' => 'Failed to update collection with image'], 500);
        // }

        return response()->json(['imageUrl' => $updatedImage]);
    }
}
