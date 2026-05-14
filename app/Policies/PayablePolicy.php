<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Payable;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayablePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Payable');
    }

    public function view(AuthUser $authUser, Payable $payable): bool
    {
        return $authUser->can('View:Payable');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Payable');
    }

    public function update(AuthUser $authUser, Payable $payable): bool
    {
        return $authUser->can('Update:Payable');
    }

    public function delete(AuthUser $authUser, Payable $payable): bool
    {
        return $authUser->can('Delete:Payable');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Payable');
    }

    public function restore(AuthUser $authUser, Payable $payable): bool
    {
        return $authUser->can('Restore:Payable');
    }

    public function forceDelete(AuthUser $authUser, Payable $payable): bool
    {
        return $authUser->can('ForceDelete:Payable');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Payable');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Payable');
    }

    public function replicate(AuthUser $authUser, Payable $payable): bool
    {
        return $authUser->can('Replicate:Payable');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Payable');
    }

}