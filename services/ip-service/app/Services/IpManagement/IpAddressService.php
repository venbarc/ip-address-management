<?php

namespace App\Services\IpManagement;

use App\Models\IpAddressRecord;
use App\Services\Audit\AuditLogger;
use App\Support\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IpAddressService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function list(): \Illuminate\Database\Eloquent\Collection
    {
        return IpAddressRecord::query()->latest()->get();
    }

    public function create(ActorContext $actor, array $attributes): IpAddressRecord
    {
        $normalizedAddress = $this->normalizeAddress($attributes['address']);

        if (IpAddressRecord::query()->where('normalized_address', $normalizedAddress)->exists()) {
            throw ValidationException::withMessages([
                'address' => 'This IP address is already recorded.',
            ]);
        }

        return DB::transaction(function () use ($actor, $attributes, $normalizedAddress): IpAddressRecord {
            $record = IpAddressRecord::query()->create([
                'address' => $attributes['address'],
                'normalized_address' => $normalizedAddress,
                'version' => str_contains($attributes['address'], ':') ? 6 : 4,
                'label' => trim($attributes['label']),
                'comment' => $attributes['comment'] ?? null,
                'created_by_user_id' => $actor->id,
                'created_by_name' => $actor->name,
                'created_by_email' => $actor->email,
                'updated_by_user_id' => $actor->id,
                'updated_by_name' => $actor->name,
                'updated_by_email' => $actor->email,
            ]);

            $this->auditLogger->record(
                event: 'ip.created',
                actor: $actor,
                subjectType: 'ip_address',
                subjectId: $record->id,
                changes: [
                    'before' => null,
                    'after' => $record->snapshot(),
                ],
            );

            return $record;
        });
    }

    public function update(ActorContext $actor, IpAddressRecord $record, array $attributes): IpAddressRecord
    {
        if (! $actor->canModifyRecordOwnedBy($record->created_by_user_id)) {
            throw new AuthorizationException('You can only modify IP addresses that you created.');
        }

        return DB::transaction(function () use ($actor, $record, $attributes): IpAddressRecord {
            $before = $record->snapshot();

            $record->forceFill([
                'label' => trim($attributes['label']),
                'comment' => $attributes['comment'] ?? null,
                'updated_by_user_id' => $actor->id,
                'updated_by_name' => $actor->name,
                'updated_by_email' => $actor->email,
            ])->save();

            $this->auditLogger->record(
                event: 'ip.updated',
                actor: $actor,
                subjectType: 'ip_address',
                subjectId: $record->id,
                changes: [
                    'before' => $before,
                    'after' => $record->fresh()->snapshot(),
                ],
            );

            return $record->fresh();
        });
    }

    public function delete(ActorContext $actor, IpAddressRecord $record): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only super-admins can delete IP addresses.');
        }

        DB::transaction(function () use ($actor, $record): void {
            $before = $record->snapshot();

            $record->delete();

            $this->auditLogger->record(
                event: 'ip.deleted',
                actor: $actor,
                subjectType: 'ip_address',
                subjectId: $before['id'],
                changes: [
                    'before' => $before,
                    'after' => null,
                ],
            );
        });
    }

    private function normalizeAddress(string $address): string
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            throw ValidationException::withMessages([
                'address' => 'The address must be a valid IPv4 or IPv6 value.',
            ]);
        }

        return bin2hex($packed);
    }
}
