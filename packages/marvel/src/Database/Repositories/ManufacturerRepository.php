<?php


namespace Marvel\Database\Repositories;

use Marvel\Database\Models\Manufacturer;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Marvel\Enums\Permission;


class ManufacturerRepository extends BaseRepository
{

    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name'        => 'like',
        'is_approved',
        'type.slug',
    ];

    protected $dataArray = [
        'name',
        'image',
        'type_id',
        'is_approved',
        'cover_image',
        'description',
        'socials',
        'website',
    ];


    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
        }
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Manufacturer::class;
    }

    public function storeManufacturer($request)
    {
        $data = $request->only($this->dataArray);
        if ($request->user()->hasPermissionTo(Permission::SUPER_ADMIN)) {
            $data['is_approved'] = true;
        } else {
            $data['is_approved'] = false;
        }
        return $this->create($data);
    }

    public function updateManufacturer($request, $author)
    {
        $author->update($request->only($this->dataArray));
        return $this->findOrFail($author->id);
    }
}
