<?php


namespace Marvel\GraphQL\Mutation;


use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Marvel\Facades\Shop;

class ManufacturerMutator
{
    public function storeManufacturer($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\ManufacturerController@store', $args);
    }
    public function updateManufacturer($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\ManufacturerController@updateManufacturer', $args);
    }
}
