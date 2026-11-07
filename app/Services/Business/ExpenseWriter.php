<?php

namespace App\Services\Business;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Payments\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseWriter
{
    public function save(User $actor, array $data, ?Expense $expense = null, ?UploadedFile $file = null): Expense
    {
        $path = null;
        $createdPaths = [];
        try {
            $result = DB::transaction(function () use ($actor, $data, $expense, $file, &$path, &$createdPaths) {
                Guard::vendor($actor);
                $saved = $expense ? Expense::whereKey($expense->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail() : null;
                if ($saved) {
                    if ($saved->voided_at) {
                        Guard::fail('expense', 'Voided expenses are retained and cannot be edited.');
                    }if ((int) $data['revision'] !== $saved->revision) {
                        Guard::fail('revision', 'Expense changed in another tab. Reload before saving.');
                    }
                }
                $amount = Money::decimal(Money::cents($data['amount']));
                $hash = hash('sha256', json_encode([(int) $data['expense_category_id'], $data['expense_date'], $amount, $data['method'], (string) ($data['reference'] ?? ''), (string) ($data['notes'] ?? ''), $file ? hash_file('sha256', $file->getRealPath()) : null], JSON_THROW_ON_ERROR));
                if (! $saved) {
                    $existing = Expense::where('vendor_id', $actor->id)->where('request_key', $data['request_key'])->first();
                    if ($existing) {
                        if (! hash_equals($existing->request_hash, $hash)) {
                            Guard::fail('request_key', 'This submission key was used for another expense. Open a new form.');
                        }

                        return $existing;
                    }
                }
                $category = ExpenseCategory::whereKey($data['expense_category_id'])->where('vendor_id', $actor->id)->lockForUpdate()->first();
                if (! $category || (! $category->is_active && (! $saved || $saved->expense_category_id !== $category->id))) {
                    Guard::fail('expense_category_id', 'Choose an active category belonging to your business.');
                }
                if ($saved) {
                    $this->event($saved, 'before-edit');
                }
                $fields = ['expense_category_id' => $category->id, 'expense_date' => $data['expense_date'], 'amount' => $amount, 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null];
                if ($saved) {
                    $saved->update(array_merge($fields, ['revision' => $saved->revision + 1]));
                } else {
                    $saved = Expense::create(array_merge($fields, ['vendor_id' => $actor->id, 'request_key' => $data['request_key'], 'request_hash' => $hash]));
                }
                if ($file) {
                    $name = Str::uuid()->toString().'.'.$file->extension();
                    $path = $file->storeAs('private/expenses/'.$actor->id, $name, 'local');
                    if (! $path) {
                        Guard::fail('attachment', 'Attachment could not be stored. No expense change was committed.');
                    }
                    $createdPaths[] = $path;
                    $original = preg_replace('/[\x00-\x1F\x7F\\\\\/]/u', '-', $file->getClientOriginalName());
                    $saved->attachments()->create(['path' => $path, 'original_name' => mb_substr($original, 0, 255), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                }
                $this->event($saved, $expense ? 'edited' : 'created');

                return $saved;
            }, 5);
            foreach ($createdPaths as $createdPath) {
                if ($createdPath !== $path) {
                    Storage::disk('local')->delete($createdPath);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            foreach ($createdPaths as $createdPath) {
                Storage::disk('local')->delete($createdPath);
            }
            throw $e;
        }
    }

    public function void(User $actor, Expense $expense, int $revision, string $reason): void
    {
        DB::transaction(function () use ($actor, $expense, $revision, $reason) {
            Guard::vendor($actor);
            $saved = Expense::whereKey($expense->id)->where('vendor_id', $actor->id)->lockForUpdate()->firstOrFail();
            if ($saved->voided_at) {
                Guard::fail('expense', 'This expense is already voided.');
            }if ($saved->revision !== $revision) {
                Guard::fail('revision', 'Expense changed. Reload before voiding.');
            }$this->event($saved, 'before-void', $reason);
            $saved->update(['voided_at' => now(), 'void_reason' => $reason, 'revision' => $saved->revision + 1]);
            $this->event($saved, 'voided', $reason);
        }, 5);
    }

    private function event(Expense $expense, string $action, ?string $reason = null): void
    {
        $expense->events()->create(['action' => $action, 'actor_id' => $expense->vendor_id, 'admin_actor_id' => Guard::admin(), 'snapshot' => $expense->getAttributes(), 'reason' => $reason]);
        app(Platform::class)->audit('expense.'.$action, 'expense', $expense->id, [], $expense->vendor_id);
    }
}
