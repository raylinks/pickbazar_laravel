<?php


namespace Marvel\Database\Repositories;

use Marvel\Database\Models\OrderedFile;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;

class DownloadRepository extends BaseRepository
{
    /**
     * Configure the Model
     **/
    public function model()
    {
        return OrderedFile::class;
    }

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
            //
        }
    }
}
