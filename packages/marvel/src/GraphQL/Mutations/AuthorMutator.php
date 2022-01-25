<?php


namespace Marvel\GraphQL\Mutation;


use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Marvel\Facades\Shop;

class AuthorMutator
{
    public function storeAuthor($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\AuthorController@store', $args);
    }
    public function updateAuthor($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\AuthorController@updateAuthor', $args);
    }
}
