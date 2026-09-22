<?php

namespace App\Http\Requests\Client\Concerns;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rule;

trait ValidatesOwnedPage
{
    protected function connectedOwnedPage(): Exists
    {
        return $this->ownedPage()->where('is_connected', true);
    }

    protected function ownedPage(): Exists
    {
        return Rule::exists('facebook_pages', 'id')->where('user_id', $this->user()->id);
    }
}
