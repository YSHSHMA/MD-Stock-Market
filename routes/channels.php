<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', function ($user, string $id): bool {
    return (string) $user->id === $id;
});
