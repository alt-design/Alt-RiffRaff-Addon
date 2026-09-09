<?php

declare(strict_types=1);

use Statamic\Facades\Role;
use Statamic\Facades\User;

it('denies access to a user without the view riffraff permission', function () {
    Role::make('cp-access')->permissions(['access cp'])->save();

    $user = User::make()->assignRole('cp-access')->save();

    $this->actingAs($user)
        ->get(cp_route('riffraff.index'))
        ->assertRedirect(cp_route('index'));
});

it('allows access to a user with the view riffraff permission', function () {
    Role::make('cp-access')->permissions(['access cp', 'view riffraff'])->save();

    $user = User::make()->assignRole('cp-access')->save();

    $this->actingAs($user)
        ->get(cp_route('riffraff.index'))
        ->assertOk();
});
