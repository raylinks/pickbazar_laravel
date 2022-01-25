<?php


namespace Marvel\GraphQL\Queries;


use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Marvel\Facades\Shop;

class AuthorQuery
{
    public function topAuthor($rootValue, array $args, GraphQLContext $context)
    {
        return Shop::call('Marvel\Http\Controllers\AuthorController@topAuthor', $args);
    }
}
