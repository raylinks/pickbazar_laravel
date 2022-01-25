<?php


namespace Marvel\GraphQL\Mutation;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Marvel\Facades\Shop;

class RefundMutator
{

    public function createRefund($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\RefundController@store', $args);
    }

    public function updateRefund($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\RefundController@updateRefund', $args);
    }
}
