<?php


namespace Marvel\GraphQL\Queries;

use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Marvel\Facades\Shop;

class ProductQuery
{
    public function relatedProducts($rootValue, array $args, GraphQLContext $context)
    {
        $args['slug'] = $rootValue->slug;
        return Shop::call('Marvel\Http\Controllers\ProductController@relatedProducts', $args);
    }
    public function fetchDigitalFilesForProduct($rootValue, array $args, GraphQLContext $context)
    {
        $args['parent_id'] = $rootValue->id;
        return Shop::call('Marvel\Http\Controllers\ProductController@fetchDigitalFilesForProduct', $args);
    }
    public function fetchDigitalFilesForVariation($rootValue, array $args, GraphQLContext $context)
    {
        $args['parent_id'] = $rootValue->id;
        return Shop::call('Marvel\Http\Controllers\ProductController@fetchDigitalFilesForVariation', $args);
    }
}
