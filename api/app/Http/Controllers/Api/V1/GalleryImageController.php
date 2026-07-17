<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGalleryImageRequest;
use App\Http\Resources\GalleryImageResource;
use App\Models\SalonGalleryImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Galeria de imagens dos atendimentos do salão. `index` é público (landing);
 * `store`/`destroy` são restritos ao dono. As imagens ficam no disk `public`
 * (mesmo padrão da imagem de serviço), com a URL pública salva em `image_url`.
 */
class GalleryImageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $images = SalonGalleryImage::query()
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return GalleryImageResource::collection($images);
    }

    public function store(StoreGalleryImageRequest $request): JsonResponse
    {
        Gate::authorize('create', SalonGalleryImage::class);

        $path = $request->file('image')->store('gallery', 'public');

        $image = SalonGalleryImage::create([
            'image_url' => Storage::url($path),
            'caption' => $request->input('caption'),
            'sort_order' => (int) SalonGalleryImage::query()->max('sort_order') + 1,
        ]);

        return (new GalleryImageResource($image))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, string $tenant, SalonGalleryImage $galleryImage): JsonResponse
    {
        Gate::authorize('delete', $galleryImage);

        // Remove o arquivo físico do disk público (image_url = /storage/<path>).
        $path = Str::after($galleryImage->image_url, '/storage/');
        if ($path !== $galleryImage->image_url) {
            Storage::disk('public')->delete($path);
        }

        $galleryImage->delete();

        return response()->json(null, 204);
    }
}
