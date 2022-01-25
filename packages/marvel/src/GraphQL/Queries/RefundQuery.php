<?php


namespace Marvel\GraphQL\Queries;


use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Marvel\Facades\Shop;

class RefundQuery
{
    public function fetchRefunds($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\RefundController@fetchRefunds', $args);
    }
}
