<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Marvel\Database\Models\DownloadToken;
use Marvel\Database\Models\OrderedFile;
use Marvel\Database\Repositories\DownloadRepository;
use Marvel\Exceptions\MarvelException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;
use Marvel\Database\Models\Variation;
use Prettus\Validator\Exceptions\ValidatorException;



class DownloadController extends CoreController
{
    public $repository;

    public function __construct(DownloadRepository $repository)
    {
        $this->repository = $repository;
    }

    public function fetchDownloadableFiles(Request $request)
    {
        $limit = isset($request->limit) ? $request->limit : 15;
        return $this->fetchFiles($request)->paginate($limit)->loadMorph('reviewable', [
            Product::class => [],
            Variation::class => ['product'],
        ])->withQueryString();
    }

    public function fetchFiles(Request $request)
    {
        $user = $request->user();
        if ($user) {
            return $this->repository->with('file.fileable')->where('customer_id', $user->id);
        }
        throw new MarvelException(NOT_AUTHORIZED);
    }


    public function generateDownloadableUrl(Request $request)
    {

        $user = $request->user();
        $orderedFiles = OrderedFile::where('digital_file_id', $request->digital_file_id)->where('customer_id', $user->id)->get();
        if (count($orderedFiles)) {
            $dataArray = [
                'user_id' => $user->id,
                'token' => Str::random(16),
                'digital_file_id' => $request->digital_file_id
            ];
            $newToken = DownloadToken::create($dataArray);
            return route('download_url.token', ['token' => $newToken->token]);
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    public function downloadFile($token)
    {
        try {
            $downloadToken = DownloadToken::with('file')->where('token', $token)->first();
            if ($downloadToken) {
                $downloadToken->delete();
            } else {
                return ['message' => TOKEN_NOT_FOUND];
            }
        } catch (Exception $e) {
            throw new MarvelException(TOKEN_NOT_FOUND);
        }
        try {
            $mediaItem = Media::findOrFail($downloadToken->file->attachment_id);
        } catch (Exception $e) {
            return ['message' => NOT_FOUND];
        }
        return $mediaItem;
    }
}
