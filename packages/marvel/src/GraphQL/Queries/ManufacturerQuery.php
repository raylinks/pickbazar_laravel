<?php


namespace Marvel\GraphQL\Queries;


use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Marvel\Facades\Shop;

class ManufacturerQuery
{
    public function topManufacturer($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\ManufacturerController@topManufacturer', $args);
    }
}
