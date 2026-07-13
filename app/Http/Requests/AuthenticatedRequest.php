<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Post;
use App\Models\User;
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
}
