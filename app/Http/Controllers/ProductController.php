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
      'description' => 'nullable|string',
      'price' => 'nullable|numeric',
      'category' => 'nullable|string',
      'image' => 'required|string',
    ]);

    try {
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
        'descriptionHtml' => $data['description'] ?? '',
        'tags' => $data['category'] ? explode(',', $data['category']) : [],
        'variants' => [
          [
            'price' => $data['price'] ?? '0.00',
          ],
        ],
      ];

      $responseCreateProduct = $shop->api()->graph($mutationCreateProduct, [
        'input' => $productInput,
      ]);

      $productCreateData = $responseCreateProduct['body']['data']['productCreate'] ?? null;

      if (isset($productCreateData['userErrors']) && count($productCreateData['userErrors']) > 0) {
        return response()->json(['errors' => $productCreateData['userErrors']], 422);
      }

      $productId = $productCreateData['product']['id'];

      // Attach media
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
          'alt' => $data['title'],
          'mediaContentType' => 'IMAGE',
          'originalSource' => $data['image'],
        ],
      ];

      $responseAttachMedia = $shop->api()->graph($mutationAttachMedia, [
        'media' => $mediaInput,
      ]);

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


  public function deleteProduct(Request $request)
  {
    $shop = Auth::user();
    $productId = $request->id;

    $mutationDeleteProduct = <<<GRAPHQL
        mutation productDelete(\$id: ID!) {
          productDelete(input: {id: \$id}) {
            deletedProductId
            userErrors {
              field
              message
            }
          }
        }
        GRAPHQL;

    $response = $shop->api()->graph($mutationDeleteProduct, ['id' => $productId]);
    Log::error('Delete Product Errors:', $response);

    return response()->json(['message' => 'Product deleted successfully']);
  }

  public function updateProduct(Request $request)
  {
    $shop = Auth::user();

    $productId = $request->id;
    $title = $request->title;
    $imageUrl = $request->image_url; // Use full image URL instead of image ID

    // Step 1: Update title
    $mutationUpdateProduct = <<<GRAPHQL
    mutation productUpdate(\$input: ProductInput!) {
      productUpdate(input: \$input) {
        product {
          id
          title
        }
        userErrors {
          field
          message
        }
      }
    }
    GRAPHQL;

    $variables = [
      'input' => [
        'id' => $productId,
        'title' => $title,
      ],
    ];

    $response = $shop->api()->graph($mutationUpdateProduct, $variables);
    Log::info('Product update response:', $response->body->container ?? []);

    // if (!empty($response['body']['data']['productUpdate']['userErrors'])) {
    //   return response()->json([
    //     'message' => 'Failed to update product',
    //     'errors' => $response['body']['data']['productUpdate']['userErrors'],
    //   ], 400);
    // }

    // Step 2: Attach image if provided
    if (!empty($imageUrl)) {
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
          'alt' => $title,
          'mediaContentType' => 'IMAGE',
          'originalSource' => $imageUrl,
        ],
      ];

      $responseAttachMedia = $shop->api()->graph($mutationAttachMedia, [
        'media' => $mediaInput,
      ]);

      \Log::info('Media attach response:', $responseAttachMedia->body->container ?? []);
    }

    return response()->json(['message' => 'Product updated successfully']);
  }
}
