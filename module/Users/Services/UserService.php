<?php

declare(strict_types=1);

namespace Module\Users\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Module\Projects\CurrentProject;
use Module\Users\DTO\UserData;
use Module\Users\DTO\UserIndexData;
use Module\Users\Repositories\UserRepository;
use Psr\Log\LoggerInterface;

final readonly class UserService
{
    public function __construct(
        private UserRepository $users,
        private CurrentProject $currentProject,
        private LoggerInterface $logger,
    ) {}

    /** @return LengthAwarePaginator<int, User> */
    public function paginate(UserIndexData $filters): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $this->currentProject->id());
    }

    public function find(User $user): User
    {
        return $this->users->find($user);
    }

    public function create(UserData $data): User
    {
        $user = $this->users->create([
            'name' => $data->name,
            'fio' => $data->fio,
            'email' => $data->email,
            'login' => $data->login,
            'external_id' => $data->externalId,
            'password' => Hash::make($data->password ?? ''),
        ]);

        $user->syncRoles($data->roles);
        $user->groups()->sync($data->groupIds);

        $projectId = $this->currentProject->id();

        if ($projectId !== null) {
            DB::table('project_users')->insertOrIgnore([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $result = $this->users->find($user);

        $this->logger->info('user.created', [
            'user_id' => $result->id,
            'email' => $result->email,
            'login' => $result->login,
            'roles' => $data->roles,
            'groups' => $data->groupIds,
            'actor' => Auth::id(),
            'payload' => $this->payload($data),
        ]);

        return $result;
    }

    public function update(UserData $data, User $user): User
    {
        $attributes = [
            'name' => $data->name,
            'fio' => $data->fio,
            'email' => $data->email,
            'login' => $data->login,
            'external_id' => $data->externalId,
        ];

        if ($data->password !== null) {
            $attributes['password'] = Hash::make($data->password);
        }

        $user = $this->users->update($user, $attributes);

        $user->syncRoles($data->roles);
        $user->groups()->sync($data->groupIds);

        $result = $this->users->find($user);

        $this->logger->info('user.updated', [
            'user_id' => $result->id,
            'email' => $result->email,
            'login' => $result->login,
            'roles' => $data->roles,
            'groups' => $data->groupIds,
            'actor' => Auth::id(),
            'payload' => $this->payload($data),
        ]);

        return $result;
    }

    public function delete(User $user): void
    {
        $this->logger->info('user.deleted', [
            'user_id' => $user->id,
            'email' => $user->email,
            'login' => $user->login,
            'actor' => Auth::id(),
        ]);

        $this->users->delete($user);
    }

    /** @return array<string, mixed> */
    private function payload(UserData $data): array
    {
        return [
            'name' => $data->name,
            'fio' => $data->fio,
            'email' => $data->email,
            'login' => $data->login,
            'external_id' => $data->externalId,
            'password' => $data->password !== null ? '[hidden]' : null,
            'roles' => $data->roles,
            'group_ids' => $data->groupIds,
        ];
    }
}
