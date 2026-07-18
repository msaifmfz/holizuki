<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ContactSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $subject
 * @property string $message
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonInterface|null $read_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'email', 'subject', 'message', 'ip_address', 'user_agent', 'read_at'])]
class ContactSubmission extends Model
{
    /** @use HasFactory<ContactSubmissionFactory> */
    use HasFactory;

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
