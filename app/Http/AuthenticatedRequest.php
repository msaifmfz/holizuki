<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

abstract class AuthenticatedRequest extends FormRequest
{
    /**
     * Get the authenticated application user.
     */
    final public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * Get the post resolved from the route binding.
     */
    protected function boundPost(): Post
    {
        $post = $this->route('post');

        if (! $post instanceof Post) {
            throw new LogicException('The post route binding is missing.');
        }

        return $post;
    }

    /**
     * Get the category resolved from the route binding.
     */
    protected function boundCategory(): Category
    {
        $category = $this->route('category');

        if (! $category instanceof Category) {
            throw new LogicException('The category route binding is missing.');
        }

        return $category;
    }

    /**
     * Get the tag resolved from the route binding.
     */
    protected function boundTag(): Tag
    {
        $tag = $this->route('tag');

        if (! $tag instanceof Tag) {
            throw new LogicException('The tag route binding is missing.');
        }

        return $tag;
    }
}
