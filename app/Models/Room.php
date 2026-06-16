<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_default', 'min_photos'];

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_room')
            ->withTimestamps()
            ->withPivot(['sort_order']);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'room_task')
            ->withTimestamps()
            ->withPivot(['sort_order', 'instructions', 'visible_to_owner', 'visible_to_housekeeper'])
            ->orderBy('room_task.sort_order');
    }

    /**
     * Clones this room (and its tasks) and attaches the clone to the given property.
     */
    public function cloneForProperty(Property $property, int $sortOrder, bool $onlyDefaultTasks = true): self
    {
        $cloned = $this->replicate();
        $cloned->is_default = false;
        $cloned->save();

        // Attach the cloned room to the property
        $property->rooms()->attach($cloned->id, ['sort_order' => $sortOrder]);

        // Clone each task independently so properties don't share task records.
        // We clone ALL tasks attached to this room (a template), because any task
        // attached to the template represents the intended default tasks for this room type.
        $tasksToClone = $this->tasks;

        foreach ($tasksToClone as $task) {
            // Do not clone occasional (sporadic) tasks into new properties
            if ($task->is_sporadic) {
                continue;
            }

            $clonedTask = $task->replicate();
            $clonedTask->is_default = false;
            $clonedTask->save();

            $cloned->tasks()->attach($clonedTask->id, [
                'sort_order'             => $task->pivot->sort_order,
                'instructions'           => $task->pivot->instructions,
                'visible_to_owner'       => $task->pivot->visible_to_owner,
                'visible_to_housekeeper' => $task->pivot->visible_to_housekeeper,
            ]);
        }

        return $cloned;
    }
}
