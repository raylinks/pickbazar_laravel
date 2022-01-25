<?php


namespace Marvel\Database\Repositories;

use Exception;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Variation;
use Marvel\Enums\ProductType;
use Marvel\Exceptions\MarvelException;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;

class ProductRepository extends BaseRepository
{

    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name'        => 'like',
        'shop_id',
        'status',
        'type.slug',
        'categories.slug' => 'in',
        'tags.slug' => 'in',
        'author.slug',
        'manufacturer.slug' => 'in',
        'min_price' => 'between',
        'max_price' => 'between',
        'price' => 'between',
    ];

    protected $dataArray = [
        'name',
        'price',
        'sale_price',
        'max_price',
        'min_price',
        'type_id',
        'author_id',
        'manufacturer_id',
        'product_type',
        'quantity',
        'unit',
        'is_digital',
        'is_external',
        'external_product_url',
        'external_product_button_text',
        'description',
        'sku',
        'image',
        'gallery',
        'status',
        'height',
        'length',
        'width',
        'in_stock',
        'is_taxable',
        'shop_id',
    ];

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
            //
        }
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Product::class;
    }

    public function storeProduct($request)
    {
        try {
            $data = $request->only($this->dataArray);
            if ($request->product_type == ProductType::SIMPLE) {
                $data['max_price'] = $data['price'];
                $data['min_price'] = $data['price'];
            }
            $product = $this->create($data);
            if (isset($request['categories'])) {
                $product->categories()->attach($request['categories']);
            }
            if (isset($request['tags'])) {
                $product->tags()->attach($request['tags']);
            }
            if (isset($request['variations'])) {
                $product->variations()->attach($request['variations']);
            }
            if (isset($request['variation_options'])) {
                foreach ($request['variation_options']['upsert'] as $variation_option) {
                    if (isset($variation_option['is_digital']) && $variation_option['is_digital']) {
                        $file = $variation_option['digital_file'];
                        unset($variation_option['digital_file']);
                    }
                    $new_variation_option = $product->variation_options()->create($variation_option);
                    if (isset($variation_option['is_digital']) && $variation_option['is_digital']) {
                        $new_variation_option->digital_file()->create($file);
                    }
                }
            }
            if (isset($request['is_digital']) && $request['is_digital'] === true && isset($request['digital_file'])) {
                $product->digital_file()->create($request['digital_file']);
            }
            return $product;
        } catch (ValidatorException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function updateProduct($request, $id)
    {
        try {
            $product = $this->findOrFail($id);
            if (isset($request['categories'])) {
                $product->categories()->sync($request['categories']);
            }
            if (isset($request['tags'])) {
                $product->tags()->sync($request['tags']);
            }
            if (isset($request['variations'])) {
                $product->variations()->sync($request['variations']);
            }
            if (isset($request['digital_file'])) {
                $file = $request['digital_file'];
                if (isset($file['id'])) {
                    $product->digital_file()->where('id', $file['id'])->update($file);
                } else {
                    $product->digital_file()->create($file);
                }
            }
            if (isset($request['variation_options'])) {
                if (isset($request['variation_options']['upsert'])) {
                    foreach ($request['variation_options']['upsert'] as $key => $variation) {
                        if (isset($variation['is_digital']) && $variation['is_digital']) {
                            $file = $variation['digital_file'];
                            unset($variation['digital_file']);
                            if (isset($variation['id'])) {
                                $product->variation_options()->where('id', $variation['id'])->update($variation);
                                try {
                                    $updated_variation = Variation::findOrFail($variation['id']);
                                } catch (Exception $e) {
                                    throw new MarvelException(NOT_FOUND);
                                }
                                if (isset($file['id'])) {
                                    $updated_variation->digital_file()->where('id', $file['id'])->update($file);
                                } else {
                                    $updated_variation->digital_file()->create($file);
                                }
                            } else {
                                $new_variation = $product->variation_options()->create($variation);
                                $new_variation->digital_file()->create($file);
                            }
                        } else {
                            if (isset($variation['id'])) {
                                $product->variation_options()->where('id', $variation['id'])->update($variation);
                            } else {
                                $product->variation_options()->create($variation);
                            }
                        }
                    }
                }
                if (isset($request['variation_options']['delete'])) {
                    foreach ($request['variation_options']['delete'] as $key => $id) {
                        try {
                            $product->variation_options()->where('id', $id)->delete();
                        } catch (Exception $e) {
                            //
                        }
                    }
                }
            }
            $data = $request->only($this->dataArray);
            if ($request->product_type == ProductType::VARIABLE) {
                $data['price'] = NULL;
                $data['sale_price'] = NULL;
                $data['sku'] = NULL;
            }
            if ($request->product_type == ProductType::SIMPLE) {
                $data['max_price'] = $data['price'];
                $data['min_price'] = $data['price'];
            }
            $product->update($data);
            if ($product->product_type === ProductType::SIMPLE) {
                $product->variations()->delete();
                $product->variation_options()->delete();
            }
            return $product;
        } catch (ValidatorException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function fetchRelated($slug, $limit = 10)
    {
        try {
            $product = $this->findOneByFieldOrFail('slug', $slug);
            $categories = $product->categories->pluck('id');
            return $this->whereHas('categories', function ($query) use ($categories) {
                $query->whereIn('categories.id', $categories);
            })->with('type')->limit($limit);
        } catch (Exception $e) {
            return [];
        }
    }
}
