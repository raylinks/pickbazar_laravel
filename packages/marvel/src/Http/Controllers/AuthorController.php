<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Marvel\Database\Models\Product;
use Marvel\Database\Repositories\AuthorRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\AuthorRequest;

class AuthorController extends CoreController
{
    public $repository;

    public function __construct(AuthorRepository $repository)
    {
        $this->repository = $repository;
    }


    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Product[]
     */
    public function index(Request $request)
    {
        $limit = $request->limit ?   $request->limit : 15;
        return $this->repository->paginate($limit);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param AuthorRequest $request
     * @return mixed
     */
    public function store(AuthorRequest $request)
    {
        if ($this->repository->hasPermission($request->user(), $request->shop_id)) {
            return $this->repository->storeAuthor($request);
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param $slug
     * @return JsonResponse
     */
    public function show(Request $request, $slug)
    {
        $request->slug = $slug;
        return $this->fetchAuthor($request);
    }

    /**
     * Display the specified resource.
     *
     * @param $slug
     * @return JsonResponse
     */
    public function fetchAuthor(Request $request)
    {
        $slug = $request->slug;
        try {
            $author = $this->repository->findOneByFieldOrFail('slug', $slug);
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
        return $author;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param AuthorRequest $request
     * @param int $id
     * @return array
     */
    public function update(AuthorRequest $request, $id)
    {
        $request->id = $id;
        return $this->updateAuthor($request);
    }

    public function updateAuthor(Request $request)
    {
        if ($this->repository->hasPermission($request->user(), $request->shop_id)) {
            try {
                $author = $this->repository->findOrFail($request->id);
            } catch (\Exception $e) {
                throw new MarvelException(NOT_FOUND);
            }
            return $this->repository->updateAuthor($request, $author);
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    public function topAuthor(Request $request)
    {
        $limit = $request->limit ? $request->limit : 10;
        return $this->repository->withCount('products')->orderBy('products_count', 'desc')->take($limit)->get();
    }
}
